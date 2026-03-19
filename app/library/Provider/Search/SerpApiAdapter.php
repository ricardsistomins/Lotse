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
     * Constructor
     *
     * @param string $apiKey
     * @param ProviderCallStorage $callStorage
     */
    public function __construct(private readonly string $apiKey, private readonly ProviderCallStorage $callStorage) {}

    /**
     * Search query via SerpApi and return results.
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search(string $query, int $limit = 10): array
    {
        $start = microtime(true);

        try {
            $client = new \GoogleSearchResults($this->apiKey);

            $data = $client->get_json([
                'engine' => 'google',
                'q'      => $query,
                'num'    => min($limit, 10),
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
                latencyMs:      $latencyMs
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
                errorMessage:   $e->getMessage()
            );

            return [];
        }
    }
}
