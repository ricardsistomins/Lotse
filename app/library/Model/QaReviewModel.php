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
}
