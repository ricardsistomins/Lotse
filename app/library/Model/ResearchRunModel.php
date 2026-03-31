<?php

namespace app\Model;

class ResearchRunModel
{
    public int     $id;
    public string  $runType;
    public string  $triggerSource;
    public string  $idempotencyKey;
    public string  $canonicalScopeKey;
    public ?string $query = null;
    public ?int    $customerId = null;
    public ?int    $reportId = null;
    public ?int    $parentRunId = null;
    public string  $providerProfileName;
    public string  $llmProviderName;
    public ?string $searchProviderName = null;
    public string  $status;
    public string  $guardrailStatus;
    public int     $retryCount;
    public ?string $errorSummary = null;
    public ?string $blockReason = null;
    public ?int    $createdByUserId = null;
    public ?string $startedAt = null;
    public ?string $finishedAt = null;
    public string  $createdAt;
    public string  $updatedAt;
    
    
    // run.status values
    const STATUS_QUEUED    = 'queued';
    const STATUS_RUNNING   = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED    = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // run.guardrail_status values
    const STATUS_PASS    = 'pass';
    const STATUS_REVIEW  = 'review';
    const STATUS_PENDING = 'pending';
    const STATUS_BLOCKED = 'blocked';
    
    // run_type possible meanings
    const RUN_TYPE_SOURCE_SYNC = 'source_sync';
    
    // trigger source values
    const TRIGGER_CRON            = 'cron';
    const TRIGGER_DASHBOARD_ADMIN = 'dashboard_admin';
    const TRIGGER_DASHBOARD_DEV   = 'dashboard_dev';
    const TRIGGER_DASHBOARD_QA    = 'dashboard_qa';
}

