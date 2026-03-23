<?php

namespace app\Model;

class ReportModel
{
    public int $id;
    public int $runId;
    public ?string $canonicalScopeKey = null;
    public string $status;
    public ?int $currentRevisionId = null;
    public ?int $approvedRevisionId = null;
    public ?int $createdByUserId = null;
    public ?int $approvedByUserId = null;
    public ?string $approvedAt = null;
    public string $createdAt;
    public string $updatedAt;

    // Extra fields from JOIN queries
    public ?string $triggerSource = null;
    public ?string $startedAt = null;
    public ?string $runGuardrailStatus = null;
    
    // Report status field options
    const STATUS_NEEDS_QA = 'needs_qa';
    const STATUS_DRAFT    = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ARCHIVED = 'archived';
}
