<?php

namespace app\Model;

class ResearchRunModel
{
    public int $id;
    public string $runType;
    public string $triggerSource;
    public string $idempotencyKey;
    public string $canonicalScopeKey;
    public ?int $customerId = null;
    public ?int $reportId = null;
    public ?int $parentRunId = null;
    public string $providerProfileName;
    public string $llmProviderName;
    public ?string $searchProviderName = null;
    public string $status;
    public string $guardrailStatus;
    public int $retryCount;
    public ?string $errorSummary = null;
    public ?int $createdByUserId = null;
    public ?string $startedAt = null;
    public ?string $finishedAt = null;
    public string $createdAt;
    public string $updatedAt;
}

