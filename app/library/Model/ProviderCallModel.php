<?php

namespace app\Model;

class ProviderCallModel
{
    public int $id;
    public int $runId;
    public string $providerKind;
    public string $providerName;
    public string $requestPurpose;
    public ?string $requestHash = null;
    public string $status;
    public ?int $inputTokens = null;
    public ?int $outputTokens = null;
    public ?int $latencyMs = null;
    public ?float $estimatedCostUsd = null;
    public ?string $errorCode = null;
    public ?string $errorMessage = null;
    public bool $fallbackUsed;
    public string $createdAt;
    public ?string $finishedAt = null;
}
