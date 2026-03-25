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

    /**
     * Run the full research pipeline.
     *
     * @param string        $triggerSource  Who triggered the run: 'dashboard_admin', 'cron', etc.
     * @param string        $query          Search query to use for source collection
     * @param int|null      $userId         ID of the user who triggered, null for cron
     * @param Mysql|null    $db             DB connection, required for audit logging
     */
    public function run(string $triggerSource, string $query, ?int $userId = null, ?Mysql $db = null): int
    {
        $settings = new SystemSettingsStorage();
        $profiles       = $settings->get('provider_profiles');
        $fallbackChain  = $settings->get('provider_fallback_chain');
        $chainNames     = $fallbackChain['chain'] ?? (array)$fallbackChain;

        $idempotencyKey     = md5($triggerSource . $query . date('YmdHi'));
        $canonicalScopeKey  = md5($query);

        // Step 1 — create run row
        $runStorage = new ResearchRunStorage();
        $existingRun = $runStorage->getByIdempotencyKey($idempotencyKey);
        
        if ($existingRun) {
            throw new DuplicateRunException($existingRun->id);
        }
        
        $runId = $runStorage->create(
            runType:              'source_sync',
            triggerSource:        $triggerSource,
            idempotencyKey:       $idempotencyKey,
            canonicalScopeKey:    $canonicalScopeKey,
            query:                $query,
            providerProfileName:  'default',
            llmProviderName:      self::PROVIDER_NAME_OPENAI,
            searchProviderName:   self::PROVIDER_NAME_SERPAPI,
            createdByUserId:      $userId
        );

        try {
            // Step 2 — collect sources via search
            $firstProfile = $profiles[$chainNames[0]] ?? [];

            if (empty($firstProfile['search']['api_key'])) {
                throw new \RuntimeException('Search provider API key is not configured for profile: ' . ($chainNames[0] ?? 'unknown'));
            }

            $callStorage   = new ProviderCallStorage();
            $searchAdapter = new SerpApiAdapter($firstProfile['search']['api_key'], $callStorage, $runId);
            $searchResults = $searchAdapter->search($query);

            // Step 3 — save sources
            $sourceStorage = new ResearchSourceStorage();
            
            foreach ($searchResults as $result) {
                $sourceStorage->save(
                    runId:           $runId,
                    sourceUrl:       $result->url,
                    sourceDomain:    parse_url($result->url, PHP_URL_HOST) ?? '',
                    sourceType:      'search_result',
                    retrievedAt:     $result->retrievedAt ?? date('Y-m-d H:i:s'),
                    sourceTitle:     $result->title,
                    providerName:    self::PROVIDER_NAME_SERPAPI,
                    capturedExcerpt: $result->snippet
                );
            }

            // Step 4 — build prompt and call LLM with fallback chain
            $sourcesText = implode("\n\n", array_map(
                fn($result) => "Title: {$result->title}\nURL: {$result->url}\nSnippet: {$result->snippet}",
                $searchResults
            ));
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

                foreach ($findings as $finding) {
                    $findingStorage->save(
                        runId:             $runId,
                        findingKey:        $this->slugify($finding['title'] ?? 'unknown'),
                        findingType:       $finding['finding_type'] ?? 'program',
                        title:             $finding['title'] ?? '',
                        normalizedPayload: $finding,
                        dedupeHash:        md5(($finding['title'] ?? '') . $runId),
                        sourceCount:       count($finding['source_urls'] ?? []),
                        confidenceScore:   (float)($finding['confidence_score'] ?? 0.0),
                        riskFlags:         $finding['risk_flags'] ?? null
                    );
                }
            }

            // Step 6 — evaluate guardrails
            $findings = $findings ?? [];
            $guardrailStatus = (new GuardrailEvaluator())->evaluate($findings, count($searchResults));

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
            (new ResearchRunStorage())->finish($runId, ResearchRunModel::STATUS_COMPLETED, guardrailStatus: $guardrailStatus);

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
        }

        return $runId;
    }

    /**
     * Build the extraction prompt from collected source text.
     */
    private function buildExtractionPrompt(string $sourcesText): string
    {
        return <<<PROMPT
You are a funding research assistant for German companies.
Analyze the following sources and extract all relevant public funding programs.
For each funding program found, return a JSON array with this exact structure:

[
  {
    "finding_key": "unique-slug-for-this-program",
    "finding_type": "program",
    "title": "Program name",
    "funding_body": "Organization providing the funding",
    "funding_amount_min": null,
    "funding_amount_max": null,
    "deadline": null,
    "eligibility": "Who can apply",
    "description": "Short summary",
    "source_urls": [],
    "confidence_score": 0.0,
    "risk_flags": []
  }
]

Return only valid JSON. No explanation text.
Sources:
{$sourcesText}
PROMPT;
    }

    /**
     * Convert a title string into a lowercase hyphenated slug for finding_key.
     */
    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        return trim($text, '-');
    }
    
    /**
    * Build the report generation prompt from extracted findings.
    */
    private function buildReportPrompt(array $findings): string
    {
        $findingsText = implode("\n\n", array_map(fn($f) => "Title: {$f['title']}\nFunding body: {$f['funding_body']}\nEligibility: {$f['eligibility']}\nDescription: {$f['description']}", $findings));

        return <<<PROMPT
You are a funding research assistant for German companies.
Based on the following extracted funding programs, write a clear and structured research
report in plain text.
Include a short introduction, then cover each program with its key details.
Write in a professional tone. Use plain text only, no markdown.

Findings:
{$findingsText}
PROMPT;
    }
}

