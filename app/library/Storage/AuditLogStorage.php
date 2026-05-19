<?php

namespace app\Storage;

use app\Model\AuditLogModel;
use app\Storage\AbstractStorage;

class AuditLogStorage extends AbstractStorage
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
        
        $sql = 'SELECT ' . $this->mapFields() . '
                FROM audit_log
                WHERE entity_type = :entityType
                AND entity_id = :entityId
                ORDER BY id DESC';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':entityType' => $entityType,
            ':entityId'   => $entityId
        ]);
        
        return $sth->fetchAll($pdo::FETCH_CLASS, AuditLogModel::class);
    }
    
    /**
     * Get all from audit_log table
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll(int $limit = 25, int $offset = 0): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT ' . $this->mapFields() . '
                FROM audit_log
                ORDER BY id DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;
        
        $sth = $pdo->prepare($sql);
        $sth->execute();
      
        return $sth->fetchAll($pdo::FETCH_CLASS, AuditLogModel::class);
    }
    
    /**
     * Count all rows in audit_log
     * 
     * @return int
     */
    public function countAll(): int
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT COUNT(*)
                FROM audit_log';
        
        $sth = $pdo->prepare($sql);
        $sth->execute();
        
        return (int)$sth->fetchColumn();
    }
}
