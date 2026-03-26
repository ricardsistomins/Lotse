<?php

namespace app\Model;

class AuditLogModel
{
    public int     $id;
    public string  $actorType;
    public ?int    $actorUserId = null;
    public string  $action;
    public string  $entityType;
    public int     $entityId;
    public ?string $beforeJson = null;
    public ?string $afterJson = null;
    public ?string $metadataJson = null;
    public string  $createdAt;
}
