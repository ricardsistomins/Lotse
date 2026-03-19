<?php

namespace app\Storage;

class ReportStorage extends AbstractStorage
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
    protected string $table = 'reports';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'                   => 'id',
        'run_id'               => 'runId',                                                   
        'status'               => 'status',
        'current_revision_id'  => 'currentRevisionId',                                       
        'created_by_user_id'   => 'createdByUserId',                                         
        'approved_by_user_id'  => 'approvedByUserId',                                        
        'approved_at'          => 'approvedAt',                                              
        'created_at'           => 'createdAt',                                               
        'updated_at'           => 'updatedAt'
    );
 
    /**
     * Create a new draft report for a run and return its ID
     * 
     * @param int $runId
     * @param int|null $createdByUserId
     * @return int
     */
    public function create(int $runId, ?int $createdByUserId = null): int
    {                                                                                        
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO reports (run_id, status, created_by_user_id)
                VALUES (:run_id, :status, :created_by_user_id)';
        
        $sth = $pdo->prepare($sql);     

        $sth->execute([                                                                      
            ':run_id'              => $runId,
            ':status'              => 'draft',                                               
            ':created_by_user_id'  => $createdByUserId,
        ]);                                                                                  

        return (int)$pdo->lastInsertId();                                                    
    }       
    
    /**
     * Update current_revision_id after a revision is saved
     * 
     * @param int $reportId
     * @param int $revisionId
     * @return void
     */
    public function setCurrentRevision(int $reportId, int $revisionId): void
    {                                                                                        
        $pdo = $this->getPdo();                                                              

        $sth = $pdo->prepare('                                                               
            UPDATE reports SET current_revision_id = :revision_id WHERE id = :id
        ');

        $sth->execute([                                                                      
            ':revision_id' => $revisionId,
            ':id'          => $reportId,                                                     
        ]);                                                                                  
    }
    
    /**
     * Update report status (approved / rejected)
     * 
     * @param int $reportId
     * @param string $status
     * @param int|null $approvedByUserId
     * @return void
     */
    public function updateStatus(int $reportId, string $status, ?int $approvedByUserId = null): void                                                                                  
    {           
        $pdo = $this->getPdo();

        $sql = 'UPDATE reports
                SET status = :status, approved_by_user_id = :approved_by, approved_at = :approved_at    
                WHERE id = :id';
        
        $sth = $pdo->prepare($sql);

        $sth->execute([                                                                      
            ':status'      => $status,
            ':approved_by' => $approvedByUserId,                                             
            ':approved_at' => $approvedByUserId ? date('Y-m-d H:i:s') : null,
            ':id'          => $reportId,                                                     
        ]);                                                                                  
    }      
    
    /**
     * Fetch a report row by ID
     * 
     * @param int $reportId
     * @return array|null
     */
    public function getById(int $reportId): ?array
    {                                                                                        
        $pdo = $this->getPdo();

        $sql = 'SELECT *
                FROM reports 
                WHERE id = :id';
        
        $sth = $pdo->prepare($sql);                        
        $sth->execute([':id' => $reportId]);

        $row = $sth->fetch($pdo::FETCH_ASSOC);                                               

        return $row ?: null;                                                                 
    }    

    /**
     * Fetch a report by its run ID
     * 
     * @param int $runId
     * @return array|null
     */
    public function getByRunId(int $runId): ?array
    {                                                                                            
        $pdo = $this->getPdo();

        $sql = 'SELECT *
                FROM reports 
                WHERE run_id = :run_id 
                LIMIT 1';
        
        $sth = $pdo->prepare($sql);            
        $sth->execute([
            ':run_id' => $runId
        ]);

        $row = $sth->fetch($pdo::FETCH_ASSOC);                                                   

        return $row ?: null;                                                                     
    }               
    
    /**                                                                                          
    * Fetch all reports with their run info, newest first.                                      
    */                                                                                          
    public function getAll(): array                                                              
    {                                                                                            
        $pdo = $this->getPdo();

        $sql = 'SELECT r.*, rr.trigger_source, rr.started_at                                         
                FROM reports r                                                                       
                JOIN research_runs rr ON rr.id = r.run_id
                ORDER BY r.id DESC';
        
        $sth = $pdo->prepare($sql);                                                                                      
        $sth->execute();                                                                         

        return $sth->fetchAll($pdo::FETCH_ASSOC);
    }
}
