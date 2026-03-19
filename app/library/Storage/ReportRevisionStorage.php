<?php

namespace app\Storage;

class ReportRevisionStorage extends AbstractStorage
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
    protected string $table = 'report_revisions';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'        => 'id',
        'report_id' => 'reportId',
        'content'   => 'content',
        'created_by_user_id' => 'createdByUserId',
        'created_at' => 'createdAt'
    );
 
    /**
     * Save a new revision and return its ID
     * 
     * @param int $reportId
     * @param string $content
     * @param int|null $createdByUserId
     * @return int
     */
    public function save(int $reportId, string $content, ?int $createdByUserId = null): int
    {                                                                                        
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO report_revisions (report_id, content, created_by_user_id)
                VALUES (:report_id, :content, :created_by_user_id)';
        
        $sth = $pdo->prepare($sql);                                                                                  

        $sth->execute([                                                                      
            ':report_id'           => $reportId,
            ':content'             => $content,
            ':created_by_user_id'  => $createdByUserId,                                      
        ]);                                                                                  

        return (int)$pdo->lastInsertId();                                                    
    }           

    /**
     * Fetch all revisions for a report, newest first
     * 
     * @param int $reportId
     * @return array
     */
    public function getAllByReportId(int $reportId): array
    {                                                                                        
        $pdo = $this->getPdo();

        $sql = 'SELECT rr.*, u.username
                FROM report_revisions rr                                                         
                LEFT JOIN users u ON u.id = rr.created_by_user_id
                WHERE rr.report_id = :report_id                                                  
                ORDER BY rr.id DESC';
        
        $sth = $pdo->prepare($sql);                                                                                  

        $sth->execute([
            ':report_id' => $reportId
        ]);

        return $sth->fetchAll($pdo::FETCH_ASSOC);                                            
    }

    /**
     * Fetch the latest revision content for a report
     * 
     * @param int $reportId
     * @return string
     */
    public function getLatestContent(int $reportId): string
    {                                                                                        
        $pdo = $this->getPdo();

        $sql = 'SELECT content FROM report_revisions                                             
                WHERE report_id = :report_id                                                     
                ORDER BY id DESC
                LIMIT 1';
        
        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':report_id' => $reportId
        ]);                                          

        return (string)$sth->fetchColumn();                                                  
    }           
}
