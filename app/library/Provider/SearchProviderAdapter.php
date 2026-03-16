<?php

namespace app\Provider;

use app\Provider\Response\SearchResult;

/**
 * Contract for all search provider implementations.
 *
 * Any search provider (Google Search, Brave Search) must implement this interface.
 * Search is optional per run — zero or one search provider may be active.
 * The run orchestrator calls search the same way regardless of which provider is used.
 */
interface SearchProviderAdapter
{
    /**
     * Execute a search query and return a list of source candidates.
     *
     * @param  string        $query  The search query to execute
     * @param  int           $limit  Maximum number of results to return
     * @return SearchResult[]
     */
    public function search(string $query, int $limit = 10): array;
}
