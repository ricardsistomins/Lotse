<?php

namespace app\Storage;

use app\Model\ReportModel;

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
        'customer_id'          => 'customerId',
        'canonical_scope_key'  => 'canonicalScopeKey',
        'status'               => 'status',
        'current_revision_id'  => 'currentRevisionId',
        'approved_revision_id' => 'approvedRevisionId',
        'created_by_user_id'   => 'createdByUserId',
        'approved_by_user_id'  => 'approvedByUserId',
        'approved_at'          => 'approvedAt',
        'created_at'           => 'createdAt',
        'updated_at'           => 'updatedAt'
    );

    /**
     * Count of reports output at page
     */
    const PER_PAGE = 5;
    
    /**
     * Create a new draft report for a run and return its ID.
     *
     * @param int $runId
     * @param string $canonicalScopeKey
     * @param int|null $createdByUserId
     * @return int
     */
    public function create(int $runId, string $canonicalScopeKey, ?int $createdByUserId = null): int
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO reports (run_id, canonical_scope_key, status, created_by_user_id)
                VALUES (:runId, :canonicalScopeKey, :status, :createdByUserId)';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':runId'             => $runId,
            ':canonicalScopeKey' => $canonicalScopeKey,
            ':status'            => ReportModel::STATUS_DRAFT,
            ':createdByUserId'   => $createdByUserId,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Update current_revision_id after a revision is saved.
     *
     * @param int $reportId
     * @param int $revisionId
     * @return void
     */
    public function setCurrentRevision(int $reportId, int $revisionId): void
    {
        $pdo = $this->getPdo();

        $sql = 'UPDATE reports
                SET current_revision_id = :revisionId
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':revisionId' => $revisionId,
            ':id'         => $reportId,
        ]);
    }

    /**
     * Update report status.
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
                SET status = :status, approved_by_user_id = :approvedBy, approved_at = :approvedAt
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status'     => $status,
            ':approvedBy' => $approvedByUserId,
            ':approvedAt' => $approvedByUserId ? date('Y-m-d H:i:s') : null,
            ':id'         => $reportId,
        ]);
    }

    /**
     * Set the approved revision for a report.
     *
     * @param int $reportId
     * @param int $revisionId
     * @return void
     */
    public function setApprovedRevision(int $reportId, int $revisionId): void
    {
        $pdo = $this->getPdo();

        $sql = 'UPDATE reports
                SET approved_revision_id = :revisionId
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':revisionId' => $revisionId,
            ':id'         => $reportId,
        ]);
    }

    /**
     * Fetch a report row by ID.
     *
     * @param int $reportId
     * @return ReportModel|null
     */
    public function getById(int $reportId): ?ReportModel
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM reports
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':id' => $reportId
        ]);

        return $sth->fetchObject(ReportModel::class) ?: null;
    }

    /**
     * Fetch a report by its run ID.
     *
     * @param int $runId
     * @return ReportModel|null
     */
    public function getByRunId(int $runId): ?ReportModel
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM reports
                WHERE run_id = :runId
                LIMIT 1';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':runId' => $runId
        ]);

        return $sth->fetchObject(ReportModel::class) ?: null;
    }

    /**
     * Fetch a report by its canonical scope key.
     *
     * @param string $scopeKey
     * @return ReportModel|null
     */
    public function getByCanonicalScopeKey(string $scopeKey): ?ReportModel
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM reports
                WHERE canonical_scope_key = :scopeKey
                LIMIT 1';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':scopeKey' => $scopeKey
        ]);

        return $sth->fetchObject(ReportModel::class) ?: null;
    }

    /**
     * Fetch all reports with their run info, newest first.
     *
     * @param string|null $status
     * @param int|null $customerId
     * @return ReportModel[]
     */
    public function getAll(?string $status = null, ?int $customerId = null, int $page = 1): array
    {
        $pdo = $this->getPdo();
        
        $where = [];
        $params = [];
        
        if ($status !== null) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
  
        if ($customerId !== null) {
            $where[] = 'customer_id = :customerId';
            $params[':customerId'] = $customerId;
        }

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM reports' . 
                ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
                ORDER BY id DESC
                LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE);

        $sth = $pdo->prepare($sql); 
        $sth->execute($params);

        return $sth->fetchAll($pdo::FETCH_CLASS, ReportModel::class);
    }

    /**
     * Fetch reports currently in the QA queue (status = needs_qa), oldest first.
     *
     * @return ReportModel[]
     */
    public function getQueueItems(): array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . ',
                       rr.guardrail_status AS runGuardrailStatus,
                       rr.started_at AS startedAt
                FROM reports
                JOIN research_runs rr ON rr.id = reports.run_id
                WHERE reports.status = :status
                ORDER BY reports.id ASC
                LIMIT 50';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => ReportModel::STATUS_NEEDS_QA
        ]);

        return $sth->fetchAll($pdo::FETCH_CLASS, ReportModel::class);
    }
    
    /**
     * Assign a customer to a report.
     *
     * @param int $reportId
     * @param int|null $customerId
     * @return void
     */
    public function updateCustomer(int $reportId, ?int $customerId): void
    {
        $pdo = $this->getPdo();

        $sql = 'UPDATE reports
                SET customer_id = :customerId
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':customerId' => $customerId,
            ':id'         => $reportId,
        ]);
    }
    
    /**
     * Update the run_id linked to a report.
     *
     * @param int $reportId
     * @param int $runId
     * @return void
     */
    public function updateRunId(int $reportId, int $runId): void
    {
        $pdo = $this->getPdo();

        $sql = 'UPDATE reports
                SET run_id = :runId 
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':runId' => $runId, 
            ':id'    => $reportId
        ]);
    }
    
    /**
     * Get report count by status
     * 
     * @return array
     */
    public function getStatusCount(): array
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT 
                    COUNT(*) AS total,
                    SUM(customer_id IS NOT NULL) AS with_customer,
                    SUM(status = :approved) AS approved,
                    SUM(status = :needs_qa) AS awaiting_qa
                FROM reports';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':approved' => ReportModel::STATUS_APPROVED,
            ':needs_qa' => ReportModel::STATUS_NEEDS_QA
        ]);
        
        $row = $sth->fetch();

        return [
            'total'         => $row['total'] ?? 0,
            'with_customer' => $row['with_customer'] ?? 0,
            'approved'      => $row['approved'] ?? 0,
            'awaiting_qa'   => $row['awaiting_qa'] ?? 0,
        ];
    }
   
    /**
     * Get count of report by status and customer id
     * 
     * @param string|null $status
     * @param int|null $customerId
     * @return int
     */
    public function getCount(?string $status = null, ?int $customerId = null): int
    {
        $pdo = $this->getPdo();
        
        $where = [];
        $params = [];
        
        if ($status !== null) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        
        if ($customerId !== null) {
            $where[] = 'customer_id = :customerId';
            $params[':customerId'] = $customerId;
        }
        
        $sql = 'SELECT COUNT(*)
                FROM reports' 
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        
        $sth = $pdo->prepare($sql);
        $sth->execute($params);
        
        return (int)$sth->fetchColumn();
    }
}
