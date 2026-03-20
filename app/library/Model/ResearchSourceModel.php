<?php

namespace app\Model;

class ResearchSourceModel
{
    public int $id;
    public int $runId;
    public string $sourceUrl;
    public string $sourceDomain;
    public ?string $sourceTitle = null;
    public string $sourceType;
    public ?string $providerName = null;
    public ?int $httpStatus = null;
    public ?string $contentHash = null;
    public ?string $capturedExcerpt = null;
    public ?string $rawPayload = null;
    public bool $isOfficial;
    public string $retrievedAt;
    public string $createdAt;
    public string $updatedAt;
}

