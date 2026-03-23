<?php

namespace app\Model;

class QaReviewModel
{
    public int $id;
    public int $reportRevisionId;
    public ?int $assignedUserId = null;
    public ?int $reviewedByUserId = null;
    public string $decisionStatus;
    public ?string $decisionNotes = null;
    public string $requestedAt;
    public ?string $decidedAt = null;
    public string $createdAt;
    public string $updatedAt;
    
    // Qa revies decision_status field options
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CHANGES_REQUESTED = 'changes_requested';
}
