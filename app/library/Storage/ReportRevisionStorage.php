<?php

namespace app\Storage;

use app\Model\ReportRevisionModel;

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
        'id'                 => 'id',
        'report_id'          => 'reportId',
        'structured_payload' => 'structuredPayload',
        'final_markdown'     => 'finalMarkdown',
        'created_by_user_id' => 'createdByUserId',
        'created_at'         => 'createdAt',
        'updated_at'         => 'updatedAt',
    );

    /**
     * Save a new revision and return its ID.
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
     * Fetch all revisions for a report with QA decisions, newest first.
     *
     * @param int $reportId
     * @return ReportRevisionModel[]
     */
    public function getAllByReportId(int $reportId): array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . ',
                       u.username,
                       qr.decision_status AS decisionStatus,
                       qr.decided_at AS decidedAt,
                       qu.username AS reviewedByUsername
                FROM report_revisions
                LEFT JOIN users u ON u.user_id = report_revisions.created_by_user_id
                LEFT JOIN qa_reviews qr ON qr.report_revision_id = report_revisions.id
                LEFT JOIN users qu ON qu.user_id = qr.reviewed_by_user_id
                WHERE report_revisions.report_id = :reportId
                ORDER BY report_revisions.id DESC';

        $sth = $pdo->prepare($sql);
        $sth->execute([':reportId' => $reportId]);

        return $sth->fetchAll($pdo::FETCH_CLASS, ReportRevisionModel::class);
    }

    /**
     * Fetch the latest revision markdown for a report.
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
     * Fetch a single revision by ID.
     *
     * @param int $revisionId
     * @return ReportRevisionModel|null
     */
    public function getById(int $revisionId): ?ReportRevisionModel
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM report_revisions
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':id' => $revisionId
        ]);

        return $sth->fetchObject(ReportRevisionModel::class) ?: null;
    }
}
