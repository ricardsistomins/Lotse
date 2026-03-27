<?php

namespace app\Model;

class ResearchFindingModel
{
    public int     $id;
    public int     $runId;
    public string  $findingKey;
    public string  $findingType;
    public string  $title;
    public ?string $normalizedPayload = null;
    public ?string $deadline = null; 
    public int     $sourceCount;
    public bool    $officialSourcePresent;
    public float   $confidenceScore;
    public ?string $riskFlags = null;
    public string  $dedupeHash;
    public string  $status;
    public string  $createdAt;
    public string  $updatedAt;
}

