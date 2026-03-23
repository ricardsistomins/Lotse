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
        $sth->execute([':scopeKey' => $scopeKey]);

        return $sth->fetchObject(ReportModel::class) ?: null;
    }

    /**
     * Fetch all reports with their run info, newest first.
     *
     * @return ReportModel[]
     */
    public function getAll(): array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . ',
                       rr.trigger_source AS triggerSource,
                       rr.started_at AS startedAt
                FROM reports
                JOIN research_runs rr ON rr.id = reports.run_id
                ORDER BY reports.id DESC
                LIMIT 100';

        $sth = $pdo->prepare($sql);
        $sth->execute();

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
}
