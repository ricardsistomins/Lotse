<?php

namespace app\Provider\Search;

use app\Provider\SearchProviderAdapter;
use app\Provider\Response\SearchResult;
use app\Storage\ProviderCallStorage;

/**
 * SerpApi Google Search implementation of SearchProviderAdapter.
 * Receives API key from the caller — adapter has no knowledge
 * of how or where credentials are stored.
 */
class SerpApiAdapter implements SearchProviderAdapter
{
    /** 
     * Maximum number of sources that can be found in a single run
     */
    const MAX_RESULTS = 20;
    
    /**
     * Constructor
     *
     * @param string $apiKey
     * @param ProviderCallStorage $callStorage
     */
    public function __construct(private readonly string $apiKey, private readonly ProviderCallStorage $callStorage, private readonly ?int $runId = null) {}

    /**
     * Search query via SerpApi and return results.
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search(string $query, int $limit = self::MAX_RESULTS): array
    {
        $start = microtime(true);

        try {
            $client = new \GoogleSearchResults($this->apiKey);

            $data = $client->get_json([
                'engine' => 'google',
                'q'      => $query,
                'num'    => min($limit, self::MAX_RESULTS),
            ]);

            $latencyMs = (int)((microtime(true) - $start) * 1000);
            $results   = [];

            foreach ($data->organic_results ?? [] as $item) {
                $results[] = new SearchResult(
                    url:         $item->link,
                    title:       $item->title,
                    snippet:     $item->snippet ?? '',
                    provider:    'serpapi',
                    retrievedAt: date('Y-m-d H:i:s'),
                );
            }

            $this->callStorage->log(
                providerKind:   'search',
                providerName:   'serpapi',
                requestPurpose: 'search',
                status:         'succeeded',
                latencyMs:      $latencyMs,
                runId:          $this->runId
            );

            return $results;
        } catch (\Throwable $e) {
            $latencyMs = (int)((microtime(true) - $start) * 1000);

            $this->callStorage->log(
                providerKind:   'search',
                providerName:   'serpapi',
                requestPurpose: 'search',
                status:         'failed',
                latencyMs:      $latencyMs,
                errorMessage:   $e->getMessage(),
                runId:          $this->runId
            );

            return [];
        }
    }
}
