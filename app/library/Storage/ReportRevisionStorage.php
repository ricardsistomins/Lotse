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
     * @param array $structuredPayload
     * @param string $finalMarkdown
     * @param int|null $createdByUserId
     * @return int
     */
    public function save(int $reportId, array $structuredPayload, string $finalMarkdown, ?int $createdByUserId = null): int
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO report_revisions
                    (report_id, structured_payload, final_markdown, created_by_user_id)
                VALUES
                    (:reportId, :structuredPayload, :finalMarkdown, :createdByUserId)';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':reportId'          => $reportId,
            ':structuredPayload' => json_encode($structuredPayload),
            ':finalMarkdown'     => $finalMarkdown,
            ':createdByUserId'   => $createdByUserId,
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
                WHERE rr.report_id = :reportId
                ORDER BY rr.id DESC';
        
        $sth = $pdo->prepare($sql);                                                                                  

        $sth->execute([
            ':reportId' => $reportId
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

        $sql = 'SELECT final_markdown
                FROM report_revisions
                WHERE report_id = :reportId
                ORDER BY id DESC
                LIMIT 1';

        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':reportId' => $reportId
        ]);                                          

        return (string)$sth->fetchColumn();                                                  
    }   
    
    /**
     * Fetch a single revision by ID
     *
     * @param int $revisionId
     * @return array|null
     */
    public function getById(int $revisionId): ?array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT *
                FROM report_revisions 
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':id' => $revisionId,
        ]);

        return $sth->fetch($pdo::FETCH_ASSOC) ?: null;
    }
}
