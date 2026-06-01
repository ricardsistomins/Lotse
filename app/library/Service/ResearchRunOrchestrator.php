<?php

namespace app\Service;

use app\Provider\LLM\OpenAIAdapter;
use app\Provider\Search\SerpApiAdapter;
use Phalcon\Db\Adapter\Pdo\Mysql;
use app\Service\DuplicateRunException; 

use app\Storage\ {
    ProviderCallStorage,
    ResearchRunStorage,
    ResearchSourceStorage,
    ResearchFindingStorage,
    SystemSettingsStorage,
    ReportStorage,
    ReportRevisionStorage,
    QaReviewStorage
};

use app\Model\{
    ReportModel,
    ResearchRunModel
};

/**
* Orchestrates the full research pipeline for a single run.
* Same code path is used for dashboard triggers and CLI cron runs.
*
* Flow:
* 1. Create research_runs row
* 2. Search for sources via search provider
* 3. Save sources to research_sources
* 4. Call LLM to extract findings from sources
* 5. Save findings to research_findings
* 6. Evaluate guardrails
* 7. Generate report via LLM (if pass or review)
* 8. Finalize run status
* 9. Write audit trail
*/
class ResearchRunOrchestrator
{
    const PROVIDER_NAME_SERPAPI = 'serpapi';
    const PROVIDER_NAME_OPENAI  = 'openai';

    public int $lastRunId = 0;
    
    /**
     * Run the full research pipeline.
     *
     * @param string     $triggerSource Who triggered the run: 'dashboard_admin', 'cron', etc.
     * @param string     $query         Search query to use for source collection
     * @param int|null   $userId        ID of the user who triggered, null for cron
     * @param Mysql|null $db            DB connection, required for audit logging
     * @param int|null   $existingRunId Resume an existing run row instead of creating a new one
     * @param string     $runType       run_type value written to research_runs (default: source_sync)
     */
    public function run(string $triggerSource, string $query, ?int $userId = null, ?Mysql $db = null, ?int $existingRunId = null, string $runType = ResearchRunModel::RUN_TYPE_SOURCE_SYNC): int
    {
        $settings = new SystemSettingsStorage();
        $profiles       = $settings->get('provider_profiles');
        $fallbackChain  = $settings->get('provider_fallback_chain');
        $chainNames     = $fallbackChain['chain'] ?? (array)$fallbackChain;

        $idempotencyKey     = md5($triggerSource . $query . date('YmdHi'));
        $canonicalScopeKey  = md5($query);

        // Step 1 — create run row
        $runStorage = new ResearchRunStorage();
        
        if ($existingRunId) {
            $runId = $existingRunId;
        } else {
            $existingRun = $runStorage->getRunningByCanonicalScopeKey($canonicalScopeKey);

            if ($existingRun) {
                throw new DuplicateRunException($existingRun->id);
            }

            $runId = $runStorage->create(
                runType:              $runType,
                triggerSource:        $triggerSource,
                idempotencyKey:       $idempotencyKey,
                canonicalScopeKey:    $canonicalScopeKey,
                query:                $query,
                providerProfileName:  'default',
                llmProviderName:      self::PROVIDER_NAME_OPENAI,
                searchProviderName:   self::PROVIDER_NAME_SERPAPI,
                createdByUserId:      $userId
            );
        }

        $this->lastRunId = $runId;
        
        try {
            // Step 2 — collect sources via search
            $firstProfile = $profiles[$chainNames[0]] ?? [];

            if (empty($firstProfile['search']['api_key'])) {
                throw new \RuntimeException('Search provider API key is not configured for profile: ' . ($chainNames[0] ?? 'unknown'));
            }

            $callStorage   = new ProviderCallStorage();
            $searchAdapter = new SerpApiAdapter($firstProfile['search']['api_key'], $callStorage, $runId, $settings->get('search_pricing') ?? []);
            $trustedDomains = $settings->get('trusted_sources') ?? [];
            $trustedUrls = [];
            $searchResults = [];

            if (!empty($trustedDomains)) {
                $trustedResults = $searchAdapter->searchByDomains($query, $trustedDomains);
                
                foreach ($trustedResults as $result) {
                    $trustedUrls[$result->url] = true;
                    $searchResults[] = $result;
                }
            }
            
            $generalResults = $searchAdapter->search($query);
            
            foreach ($generalResults as $result) {
                if (!isset($trustedUrls[$result->url])) {
                    $searchResults[] = $result;
                }
            }
            
            $sourceTexts = [];
            $deadUrls = [];
            
            foreach ($searchResults as $result) {
                $fetched = $this->fetchSourceText($result->url, $result->snippet);
                $sourceTexts[$result->url] = $fetched;
                
                if ($fetched === $result->snippet) {
                    $deadUrls[$result->url] = true;
                }
            }
            
            // Step 3 — save sources            
            $sourceStorage = new ResearchSourceStorage();
            
            foreach ($searchResults as $result) {
                $sourceStorage->save(
                    runId:           $runId,
                    sourceUrl:       $result->url,
                    sourceDomain:    parse_url($result->url, PHP_URL_HOST) ?? '',
                    sourceType:      isset($trustedUrls[$result->url]) ? 'official' : 'search_result',
                    retrievedAt:     $result->retrievedAt ?? date('Y-m-d H:i:s'),
                    sourceTitle:     $result->title,
                    providerName:    self::PROVIDER_NAME_SERPAPI,
                    capturedExcerpt: $result->snippet,
                    sourceText:      $sourceTexts[$result->url] ?? null,
                    readFailed:      isset($deadUrls[$result->url])
                );
            }   
            
            // Step 4 — build prompt and call LLM with fallback chain
            $parts = [];
            
            foreach ($searchResults as $result) {
                if (isset($deadUrls[$result->url])) {
                    continue;
                }
                
                $officialTag = isset($trustedUrls[$result->url]) ? '[OFFICIAL SOURCE] ' : '';
                
                $parts[] = 'Title: '   . $officialTag . $result->title . "\n" .
                           'URL: '     . $result->url   . "\n" .
                           'Content: ' . $sourceTexts[$result->url];
            }

            $sourcesText = implode("\n\n", $parts);
            
            $prompt = $this->buildExtractionPrompt($sourcesText);
            $llmResponse = null;
            $isFallback = false;

            foreach ($chainNames as $profileName) {
                $profileData = $profiles[$profileName] ?? null;

                if (!$profileData) {
                    continue;
                }

                $llmAdapter  = new OpenAIAdapter(
                    $profileData['llm']['api_key'],
                    $profileData['llm']['model'],
                    $callStorage,
                    $settings->get('llm_pricing') ?? []
                );

                $llmResponse = $llmAdapter->complete($prompt, [
                    'purpose'       => 'findings_extraction',
                    'run_id'        => $runId,
                    'fallback_used' => $isFallback,
                ]);

                if ($llmResponse->success) {
                    break;
                }

                $isFallback = true;
            }

            // Step 5 — parse and save findings
            $findingStorage = new ResearchFindingStorage();

            if ($llmResponse?->success) {
                $raw = preg_replace('/^```(?:json)?\s*/m', '', $llmResponse->content);   
                $raw = preg_replace('/```\s*$/m', '', $raw);                             
                $findings = json_decode(trim($raw), true) ?? [];   

                $today = date('Y-m-d');
                $todayTs = strtotime($today);
                $result = [];
                
                foreach ($findings as $finding) {
                    $deadline = $finding['deadline'] ?? null;
                    $applicationStatus = $finding['application_status'] ?? 'unknown';
                    
                    if ($applicationStatus === 'closed') {
                        continue;
                    }
                    
                    if ($deadline && strtotime($deadline) < $todayTs) {
                        continue;
                    }
                    
                    $result[] = $finding;
                }
                               
                $findings = $result;
                
                foreach ($findings as &$finding) {
                    $isOfficials = $finding['source_is_official'] ?? [];
                    $hasOfficial = in_array(true, array_map('boolval', $isOfficials), true);
                    
                    if (!$hasOfficial) {
                        $finding['risk_flags'][] = 'no_official_source';
                    }
                    
                    if (!empty($finding['co_funders'])) {
                        $finding['risk_flags'][] = 'co_funders_present';
                    }
                }
                
                unset($finding);
                
                foreach ($findings as $finding) {
                    $findingStorage->save(
                        runId:             $runId,
                        findingKey:        $this->slugify($finding['title'] ?? 'unknown'),
                        findingType:       $finding['finding_type'] ?? 'program',
                        title:             $finding['title'] ?? '',
                        normalizedPayload: $finding,
                        dedupeHash:        md5(($finding['title'] ?? '') . $runId),
                        coFunders:         $finding['co_funders'] ?? null,
                        deadline:          $finding['deadline'] ?? null,
                        sourceCount:       count($finding['source_urls'] ?? []),
                        confidenceScore:   (float)($finding['confidence_score'] ?? 0.0),
                        riskFlags:         $finding['risk_flags'] ?? null
                    );
                }
                
                foreach ($findings as $finding) {
                    $urls = $finding['source_urls'] ?? [];
                    $isOfficials = $finding['source_is_official'] ?? [];
                    
                    foreach ($urls as $i => $url) {
                        $isOfficial = isset($trustedUrls[$url]) ? true : (bool)($isOfficials[$i] ?? false);
                        $sourceStorage->setIsOfficial($runId, $url, $isOfficial);
                    }             
                }
    
                if (!empty($findings) && isset($llmAdapter)) {
                    $validationPrompt = $this->buildValidationPrompt($findings, $sourcesText); 
                    
                    $validationResponse = $llmAdapter->complete($validationPrompt, [
                        'purpose' => 'findings_validation',
                        'run_id'  => $runId,
                        'fallback_used' => $isFallback
                    ]);
                    
                    if ($validationResponse->success) {
                        $raw = preg_replace('/^```(?:json)?\s*/m', '', $validationResponse->content);
                        $raw = preg_replace('/```\s*$/m', '', $raw);
                        $validationIssues = json_decode(trim($raw), true) ?? [];

                        $issuesByKey = [];

                        foreach ($validationIssues as $vi) {
                            $key = $vi['finding_key'] ?? null;

                            if ($key && !empty($vi['issues'])) {
                                $issuesByKey[$key] = $vi['issues'];
                            }
                        }

                        foreach ($findings as &$finding) {
                            $key = $finding['finding_key'] ?? null;

                            if ($key && isset($issuesByKey[$key])) {
                                $finding['risk_flags'] = array_merge(
                                    $finding['risk_flags'] ?? [],
                                    $issuesByKey[$key]
                                );
                            }
                        }

                        unset($finding);
                    }
                }
            }

            // Step 6 — evaluate guardrails
            $findings = $findings ?? [];
            $guardrailResult = (new GuardrailEvaluator())->evaluate($findings, count($searchResults));
            $guardrailStatus = $guardrailResult['status'];
            $blockReason = $guardrailResult['reason'];
            
            // Step 7 — generate report if guardrail allows
            if (isset($llmAdapter) && in_array($guardrailStatus, [GuardrailEvaluator::STATUS_PASSED, GuardrailEvaluator::STATUS_REVIEW])) {
                $reportPrompt   = $this->buildReportPrompt($findings);
                $reportResponse = $llmAdapter->complete($reportPrompt, ['purpose' => 'report_generation', 'run_id' => $runId, 'fallback_used' => $isFallback]);

                if ($reportResponse->success) {
                    $savedFindings     = $findingStorage->getAllByRunId($runId);
                    
                    $structuredPayload = array_map(fn($f) => [
                        'title'        => $f->title,
                        'finding_type' => $f->findingType,
                        'finding_key'  => $f->findingKey,
                        'confidence'   => $f->confidenceScore,
                        'normalized'   => json_decode($f->normalizedPayload, true),
                    ], $savedFindings);

                    $reportStorage = new ReportStorage();
                    $report        = $reportStorage->getByCanonicalScopeKey($canonicalScopeKey);
                    
                    if ($report) {
                        $reportId = $report->id;
                        $reportStorage->updateRunId($reportId, $runId);
                    } else {
                        $reportId = $reportStorage->create($runId, $canonicalScopeKey, $userId);
                    }
        
                    $revisionId = (new ReportRevisionStorage())->save($reportId, $structuredPayload, $reportResponse->content, $userId);
                    $reportStorage->setCurrentRevision($reportId, $revisionId);
                    $reportStorage->updateStatus($reportId, ReportModel::STATUS_NEEDS_QA); 
                    
                    (new QaReviewStorage())->create($revisionId);
                    
                    (new AuditService($db))->log(
                        actorType:   $userId ? 'user' : 'system',
                        actorUserId: $userId,
                        action:      'report.created',
                        entityType:  'report',
                        entityId:    $reportId,
                        metadata:    [
                            'run_id' => $runId, 
                            'guardrail_status' => $guardrailStatus
                        ]
                    );
                }
            }

            // Step 8 — finalize run
            (new ResearchRunStorage())->finish($runId, ResearchRunModel::STATUS_COMPLETED, guardrailStatus: $guardrailStatus, blockReason: $blockReason);

            // Step 9 — log audit
            (new AuditService($db))->log(
                actorType:   $userId ? 'user' : 'system',
                actorUserId: $userId,
                action:      'run.completed',
                entityType:  'research_run',
                entityId:    $runId,
                metadata:    ['guardrail_status' => $guardrailStatus]
            );
        } catch (\Throwable $e) {
            (new ResearchRunStorage())->finish($runId, ResearchRunModel::STATUS_FAILED, $e->getMessage());

            (new AuditService($db))->log(
                actorType:   $userId ? 'user' : 'system',
                actorUserId: $userId,
                action:      'run.failed',
                entityType:  'research_run',
                entityId:    $runId,
                metadata:    ['error' => $e->getMessage()]
            );
            
            throw $e;
        }

        return $runId;
    }

    /**
     * Create a run row and dispatch execution to a background CLI process.
     * Returns the run ID immediately without waiting for the run to complete.
     * 
     * @param string $triggerSource
     * @param string $query
     * @param int|null $userId
     * @return int
     * @throws DuplicateRunException
     */
    public function dispatch(string $triggerSource, string $query, ?int $userId = null, string $runType = ResearchRunModel::RUN_TYPE_SOURCE_SYNC): int
    {
        $idempotencyKey = md5($triggerSource . $query . date('YmdHi'));
        $canonicalScopeKey = md5($query);
        
        $researchRunStorage = new ResearchRunStorage();
        $existingRun = $researchRunStorage->getRunningByCanonicalScopeKey($canonicalScopeKey);
        
        if ($existingRun) {
            throw new DuplicateRunException($existingRun->id);
        }
        
        $runId = $researchRunStorage->create(
          runType:             $runType,
          triggerSource:       $triggerSource,
          idempotencyKey:      $idempotencyKey,
          canonicalScopeKey:   $canonicalScopeKey,
          query:               $query,
          providerProfileName: 'default',
          llmProviderName:     self::PROVIDER_NAME_OPENAI,
          searchProviderName:  self::PROVIDER_NAME_SERPAPI,
          createdByUserId:     $userId
      );    

      $cmd = 'php ' . BASE_PATH . '/cli.php run execute ' . $runId . ' > /dev/null 2>&1 &';
      exec($cmd);
      
      return $runId;
    }
    
    /**
     * Build the extraction prompt from collected source text.
     * 
     * @param string $sourcesText
     * @return string 
     */
    private function buildExtractionPrompt(string $sourcesText): string
    {
        $today = date('Y-m-d');              
        
        return <<<PROMPT
You are a funding research assistant for German companies.
Analyze the following sources and extract only funding programs relevant to German companies. 
Ignore any programs that are not available in Germany or to German-registered companies.
EU-wide programs should only be included if they are directly accessible to German-registered companies.

Today's date is {$today}.
Do NOT include programs whose application deadline has already passed.
If a program has ended or is no longer accepting applications, skip it entirely.           

For each active funding program found, return a JSON array with this exact structure:      

[
  {
    "finding_key": "unique-slug-for-this-program", 
    "finding_type": "program",
    "title": "Program name",
    "funding_body": "Organization providing the funding",
    "co_funders": [],
    "funding_amount_min": null,
    "funding_amount_max": null,
    "deadline": null,
    "application_status": "open",
    "eligibility": "Who can apply",
    "description": "Short summary",
    "source_urls": [],
    "source_is_official": [],
    "confidence_score": 0.0,
    "risk_flags": []
  }
]

deadline must be in YYYY-MM-DD format, or null if unknown.
"application_status" must be one of: "open", "closed", "unknown".
Set to "closed" if the source indicates the program has ended or is no longer accepting applications.               
"source_is_official" must be a parallel array to "source_urls"
- if a source title is prefixed with [OFFICIAL SOURCE], always set true for that source
- otherwise set true for official pages (government, foundation, university, or direct programme pages),
- false for secondary sources (blogs, news articles, aggregators).
"co_funders" must be an array of all additional organisations co-funding this programme alongside the main funding_body. Leave empty if there is only one funder.
Return only valid JSON. No explanation text.
Sources:
{$sourcesText}
PROMPT;
    }

    /**
     * Fetch and clean the full text of a source URL for use in the extraction prompt.
     * Converts structural HTML tags to plain-text equivalents before stripping, 
     * 
     * @param string $url
     * @param string $fallback
     * @return string
     */
    private function fetchSourceText(string $url, string $fallback): string
    {
        $ctx = stream_context_create(['http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (compatible; LotseBot/1.0)',
            'ignore_errors' => true
        ]]);
        
        $html = @file_get_contents($url, false, $ctx);
        
        if (!$html) {
            return $fallback;
        }
        
        // Treat 4xx/5xx responses as dead pages
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/HTTP\/\S+\s+(\d{3})/', $statusLine, $m);
        $statusCode = (int)($m[1] ?? 200);
        
        if ($statusCode >= 400) {
            return $fallback;
        }
        
        // Strip <head>, <script>, <style>, <nav>, <footer>, <header> blocks entirely, noise removal
        $html = preg_replace('/<(head|script|style|nav|footer|header)[^>]*>.*?<\/\1>/si', '', $html);         
        // Save document structure as plain-text markers before stripping tags 
        $html = preg_replace('/<(h[1-6])[^>]*>/i', "\n## ", $html); // headings
        $html = preg_replace('/<\/(h[1-6])>/i', "\n", $html);                                  
        $html = preg_replace('/<(p|br|\/tr)[^>]*>/i', "\n", $html); // paragraphs and table rows                           
        $html = preg_replace('/<(td|th)[^>]*>/i', "\t", $html); // table cells
        $html = preg_replace('/<(li|dt)[^>]*>/i', "\n• ", $html); // list items                             

        $text = strip_tags($html);                                                             
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalise whitespace while keeping line and paragraph breaks
        $text = preg_replace('/\t+/', "\t", $text);                                            
        $text = preg_replace('/[ \t]*\n[ \t]*/m', "\n", $text);                                
        $text = preg_replace('/\n{3,}/', "\n\n", $text);                                       
        $text = trim($text);        
        
        return mb_substr($text, 0, 6000);
    }
    
    /**
     * Convert a title string into a lowercase hyphenated slug for finding_key.
     * 
     * @param string $text
     * @return string 
     */
    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        return trim($text, '-');
    }
    
    /**
    * Build the report generation prompt from extracted findings.
    * 
    * @param array $findings
    * @return string 
    */
    private function buildReportPrompt(array $findings): string
    {    
        $today = date('Y-m-d');
        $parts = [];
        
        foreach ($findings as $finding) {
            $parts[] = 'Title: '        . $finding['title']        . "\n" .
                       'Funding body: ' . $finding['funding_body'] . "\n" .
                       'Eligibility: '  . $finding['eligibility']  . "\n" .
                       'Description: '  . $finding['description']  . "\n";
        }
        
        $findingsText = implode("\n\n", $parts);
        
        return <<<PROMPT
You are a funding research assistant for German companies.
Today's date is {$today}. Only include programs that are currently active and accepting applications. 
Do not mention any programs that have ended or whose deadlines have passed. 
Based on the following extracted funding programs, write a clear and structured research report in plain text.
Include a short introduction, then cover each program with its key details.
Write in a professional tone. Use plain text only, no markdown.
Write naturally, as a human expert would avoid repetitive phrasing, overly formal structures, and AI-sounding patterns. 
    The report should read as if written by an experienced funding consultant.

Findings:
{$findingsText}
PROMPT;
    }
    
    /**
     * Build the validation prompt that cross-checks extracted findings against source text.
     *
     * @param array $findings
     * @param string $sourcesText
     * @return string
     */
    private function buildValidationPrompt(array $findings, string $sourcesText): string
    {
        $findingsJson = json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
         return <<<PROMPT
You are a funding research quality auditor.                                                
Review the extracted funding program findings below against the source materials.
For each finding, check whether:                                                           
1. The program title and funding body are actually mentioned in the sources                
2. The funding amount (if stated) is consistent with what the sources say                  
3. The eligibility criteria match the source content                                       
4. The deadline (if stated) appears in the sources                                         
5. Any claims in the description are supported by the source content                       

Return a JSON array containing only findings that have issues. If a finding is accurate,   
omit it entirely.                                                                          

[                                                                                          
  {             
    "finding_key": "the-finding-key",
    "issues": ["Short description of issue 1", "Short description of issue 2"]             
  }                                                                                        
]                                                                                          

Return only valid JSON. No explanation text.

Findings:
{$findingsJson}                                                                            

Sources:
{$sourcesText}
PROMPT;
    }
}

