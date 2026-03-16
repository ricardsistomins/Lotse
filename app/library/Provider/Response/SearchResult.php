<?php

namespace app\Provider\Response;

/**
* Represents a single source candidate returned by a search provider.
* Each result maps to a potential research_sources row in the database.
*/
class SearchResult
{
    /**
     * Construct
     * 
     * @param string $url
     * @param string $title
     * @param string $snippet
     * @param string $provider
     * @param string|null $retrievedAt
     */
    public function __construct(public readonly string $url, public readonly string $title, public readonly string $snippet, public readonly string $provider, public readonly ?string $retrievedAt = null) {}
}

