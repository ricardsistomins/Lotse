<?php

namespace app\Provider\Search;

use app\Provider\SearchProviderAdapter;
use app\Provider\Response\SearchResult;
use app\Storage\ProviderCallStorage;
use GuzzleHttp\Client;

/**
 * Google Custom Search API implementation of SearchProviderAdapter.
 * Receives credentials and search engine ID from the caller — adapter has
 * no knowledge of how or where credentials are stored.
 */
class GoogleSearchAdapter implements SearchProviderAdapter
{
    private Client $client;

    /**
     * Constructor
     * 
     * @param string $apiKey
     * @param string $searchEngineId
     * @param ProviderCallStorage $callStorage
     */
    public function __construct(private readonly string $apiKey, private readonly string $searchEngineId, private readonly ProviderCallStorage $callStorage) 
    {
        $this->client = new Client([
            'base_uri' => 'https://www.googleapis.com/customsearch/v1',
            'timeout'  => 10,
        ]);
    }

    /**
     * Search query to google search engine by API
     * 
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search(string $query, int $limit = 10): array
    {
        $start = microtime(true);
        
        try {
            $response = $this->client->get('', [
                'query' => [
                    'key' => $this->apiKey,
                    'cx'  => $this->searchEngineId,
                    'q'   => $query,
                    'num' => min($limit, 10),
                ],
            ]);

            $latencyMs = (int)((microtime(true) - $start) * 1000);
            $data    = json_decode($response->getBody()->getContents(), true);
            $results = [];

            foreach ($data['items'] ?? [] as $item) {
                $results[] = new SearchResult(
                    url:         $item['link'],
                    title:       $item['title'],
                    snippet:     $item['snippet'] ?? '',
                    provider:    'google',
                    retrievedAt: date('Y-m-d H:i:s'),
                );
            }

            $this->callStorage->log(
                providerKind:   'search',
                providerName:   'google',
                requestPurpose: 'search',
                status:         'succeeded',
                latencyMs:      $latencyMs
            );
            
            return $results;
        } catch (\Throwable $e) {
            $latencyMs = (int)((microtime(true) - $start) * 1000);
             
            $this->callStorage->log(
                providerKind:   'search',
                providerName:   'google',
                requestPurpose: 'search',
                status:         'failed',
                latencyMs:      $latencyMs,
                errorMessage:   $e->getMessage()
            );

            return [];
        }
    }
}
