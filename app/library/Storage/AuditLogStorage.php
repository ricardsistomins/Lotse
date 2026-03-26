<?php

namespace app\Storage;

use app\Model\AuditLogModel;
use app\Storage\AbstractStorage;

abstract class AuditLogStorage extends AbstractStorage
{
    /**
     * Primary key
     *
     * @var string
     */
    protected string $primary = 'id';

    /**
     * Table name
     *
     * @var string
     */
    protected string $table = 'audit_log';
    
    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'            => 'id',
        'actor_type'    => 'actorType',                                        
        'actor_user_id' => 'actorUserId',
        'action'        => 'action',                                           
        'entity_type'   => 'entityType',                                       
        'entity_id'     => 'entityId',
        'before_json'   => 'beforeJson',                                       
        'after_json'    => 'afterJson',
        'metadata_json' => 'metadataJson',                                     
        'created_at'    => 'createdAt'
    );
    
    /**
     * Fetch all audit log entries for a given entity, newest first.
     * 
     * @param string $entityType
     * @param int $entityId
     * @return array
     */
    public function getAllByEntity(string $entityType, int $entityId): array
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT' . $this->mapFields() . '
                FROM audit_log
                WHERE entity_type = :entityType
                AND entity = :entityId
                ORDER BY id DESC';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':entityType' => $entityType,
            ':entityId'   => $entityId
        ]);
        
        return $sth->fetchAll($pdo::FETCH_CLASS, AuditLogModel::class);
    }
}
