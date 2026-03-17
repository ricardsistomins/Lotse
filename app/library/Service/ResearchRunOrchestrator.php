<?php

namespace app\Service;

use app\Provider\LLM\OpenAIAdapter;
use app\Provider\Search\GoogleSearchAdapter;
use app\Storage\ProviderCallStorage;
use app\Storage\ResearchRunStorage;
use app\Storage\ResearchSourceStorage;
use app\Storage\ResearchFindingStorage;
use app\Storage\SystemSettingsStorage;
use Phalcon\Db\Adapter\Pdo\Mysql;

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
* 6. Finalize run status
* 7. Write audit trail
*/
class ResearchRunOrchestrator
{
    private ResearchRunStorage     $runStorage;
    private ResearchSourceStorage  $sourceStorage;
    private ResearchFindingStorage $findingStorage;
    private ProviderCallStorage    $callStorage;
    private AuditService           $auditService;

    public function __construct(private readonly Mysql $db)
    {
        $this->runStorage     = new ResearchRunStorage();
        $this->sourceStorage  = new ResearchSourceStorage();
        $this->findingStorage = new ResearchFindingStorage();
        $this->callStorage    = new ProviderCallStorage();
        $this->auditService   = new AuditService($db);
    }

    /**
     * Run the full research pipeline.
     *
     * @param string   $triggerSource  Who triggered the run: 'dashboard_admin', 'cron', etc.
     * @param string   $query          Search query to use for source collection
     * @param int|null $userId         ID of the user who triggered, null for cron
     */
    public function run(string $triggerSource, string $query, ?int $userId = null): int 
    {
        $settings = new SystemSettingsStorage();
        $profile  = $settings->get('provider_profiles')['default'];

        $idempotencyKey     = md5($triggerSource . $query . date('YmdHi'));
        $canonicalScopeKey  = md5($query);

        // Step 1 — create run row
        $runId = $this->runStorage->create(
            runType:              'source_sync',
            triggerSource:        $triggerSource,
            idempotencyKey:       $idempotencyKey,
            canonicalScopeKey:    $canonicalScopeKey,
            providerProfileName:  'default',
            llmProviderName:      'openai',
            searchProviderName:   'google',
            createdByUserId:      $userId
        );

        try {
            // Step 2 — collect sources via search
            $searchAdapter = new GoogleSearchAdapter(
                $profile['search']['api_key'],
                $profile['search']['search_engine_id'],
                $this->callStorage
            );

            $searchResults = $searchAdapter->search($query);

            // Step 3 — save sources
            foreach ($searchResults as $result) {
                $this->sourceStorage->save(
                    runId:           $runId,
                    sourceUrl:       $result->url,
                    sourceDomain:    parse_url($result->url, PHP_URL_HOST) ?? '',
                    sourceType:      'search_result',
                    retrievedAt:     $result->retrievedAt ?? date('Y-m-d H:i:s'),
                    sourceTitle:     $result->title,
                    providerName:    'google',
                    capturedExcerpt: $result->snippet
                );
            }

            // Step 4 — build prompt and call LLM
            $sourcesText = implode("\n\n", array_map(
                fn($r) => "URL: {$r->url}\nTitle: {$r->title}\nExcerpt: {$r->snippet}",
                $searchResults
            ));

            $prompt = $this->buildExtractionPrompt($sourcesText);

            $llmAdapter = new OpenAIAdapter(
                $profile['llm']['api_key'],
                $profile['llm']['model'],
                $this->callStorage,
            );

            $llmResponse = $llmAdapter->complete($prompt, ['purpose' => 'findings_extraction']);

            // Step 5 — parse and save findings
            if ($llmResponse->success) {
                $findings = json_decode($llmResponse->content, true) ?? [];

                foreach ($findings as $finding) {
                    $this->findingStorage->save(
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

            // Step 6 — finalize run
            $this->runStorage->finish($runId, 'completed');

            // Step 7 — log audit
            $this->auditService->log(
                actorType:   $userId ? 'user' : 'system',
                actorUserId: $userId,
                action:      'run.completed',
                entityType:  'research_run',
                entityId:    $runId
            );

        } catch (\Throwable $e) {
            $this->runStorage->finish($runId, 'failed', $e->getMessage());

            $this->auditService->log(
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
}


