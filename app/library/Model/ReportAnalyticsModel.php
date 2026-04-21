<?php

namespace app\Model;

class ReportAnalyticsModel                                                           
{
    public int     $id;                                                           
    public int     $reportId;
    public int     $revisionId;
    public ?string $analyticsPayload = null;
    public string  $createdAt;
}                                         