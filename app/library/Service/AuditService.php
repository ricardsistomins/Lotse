<?php

namespace app\Service;

use Phalcon\Db\Adapter\Pdo\Mysql;

class AuditService
{
    public function __construct(private Mysql $db) {}

    /**
     * Writes a record to the audit log
     * 
     * @param string $actorType
     * @param int|null $actorUserId
     * @param string $action
     * @param string $entityType
     * @param int $entityId
     * @param mixed $before
     * @param mixed $after
     * @param mixed $metadata
     * @return void
     */
    public function log(string $actorType, ?int $actorUserId, string $action, string $entityType, int $entityId, mixed $before = null, mixed $after = null, mixed  $metadata = null): void 
    {
        $this->db->insertAsDict('audit_log', [
            'actor_type'    => $actorType,
            'actor_user_id' => $actorUserId,
            'action'        => $action,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'before_json'   => $before   !== null ? json_encode($before)   : null,
            'after_json'    => $after    !== null ? json_encode($after)    : null,
            'metadata_json' => $metadata !== null ? json_encode($metadata) : null,
        ]);
    }
}
