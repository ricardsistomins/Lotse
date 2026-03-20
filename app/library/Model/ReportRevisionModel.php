<?php

namespace app\Model;

class ReportRevisionModel
{
    public int $id;
    public int $reportId;
    public ?string $structuredPayload = null;
    public ?string $finalMarkdown = null;
    public ?int $createdByUserId = null;
    public string $createdAt;
    public string $updatedAt;

    // Extra fields from JOIN queries
    public ?string $username = null;
    public ?string $decisionStatus = null;
    public ?string $decidedAt = null;
    public ?string $reviewedByUsername = null;
}
