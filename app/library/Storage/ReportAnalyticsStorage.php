<?php

namespace app\Storage;

use app\Model\ReportAnalyticsModel;

class ReportAnalyticsStorage extends AbstractStorage
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
    protected string $table = 'report_analytics';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'                => 'id',
        'report_id'         => 'reportId',
        'revision_id'       => 'revisionId',
        'analytics_payload' => 'analyticsPayload',
        'created_at'        => 'createdAt',
    );

    /**
     * Save analytics data
     * 
     * @param int $reportId
     * @param int $revisionId
     * @param array $analyticsPayload
     * @return int
     */
    public function save(int $reportId, int $revisionId, array $analyticsPayload): int
    {
        $pdo = $this->getPdo();
        
        $sql = 'INSERT INTO report_analytics
                    (report_id, revision_id, analytics_payload)
                VALUES
                    (:reportId, :revisionId, :analyticsPayload)';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':reportId'         => $reportId,
            ':revisionId'       => $revisionId,
            ':analyticsPayload' => json_encode($analyticsPayload)
        ]);
        
        return (int)$pdo->lastInsertId();
    }
    
    /**
     * Get revision by id
     * 
     * @param int $revisionId
     * @return ReportAnalyticsModel|null
     */
    public function getByRevisionId(int $revisionId): ?ReportAnalyticsModel
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT ' . $this->mapFields() . '
                FROM report_analytics
                WHERE revision_id = :revisionId
                LIMIT 1';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':revisionId' => $revisionId
        ]);
        
        return $sth->fetchObject(ReportAnalyticsModel::class) ?: null;
    }
}
