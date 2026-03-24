<?php

namespace app\Storage;

use app\Model\{
    CustomerModel,
    ResearchRunModel,
    ReportModel
};

class DashboardStorage extends AbstractStorage
{
    /**
     * Count active customers.
     *
     * @return int
     */
    public function countActiveCustomers(): int
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*)
                FROM customers 
                WHERE status = :status';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => CustomerModel::STATUS_ACTIVE
        ]);

        return (int)$sth->fetchColumn();
    }

    /**
     * Count runs currently in progress.
     *
     * @return int
     */
    public function countRunsInProgress(): int
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*)
                FROM research_runs 
                WHERE status = :status';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => ResearchRunModel::STATUS_RUNNING
        ]);

        return (int)$sth->fetchColumn();
    }

    /**
     * Count runs blocked by guardrails.
     *
     * @return int
     */
    public function countBlockedRuns(): int
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*) 
                FROM research_runs 
                WHERE guardrail_status = :status';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => ResearchRunModel::STATUS_BLOCKED
        ]);

        return (int)$sth->fetchColumn();
    }

    /**
     * Count reports awaiting QA review.
     *
     * @return int
     */
    public function countReportsAwaitingQa(): int
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*)
                FROM reports 
                WHERE status = :status';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => ReportModel::STATUS_NEEDS_QA
        ]);

        return (int)$sth->fetchColumn();
    }

    /**
     * Count approved reports.
     *
     * @return int
     */
    public function countApprovedReports(): int
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*)
                FROM reports 
                WHERE status = :status';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => ReportModel::STATUS_APPROVED
        ]);

        return (int)$sth->fetchColumn();
    }

    /**
     * Count provider call failures in the last 24 hours.
     *
     * @return int
     */
    public function countProviderFailuresLast24h(): int
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*)
                FROM provider_calls
                WHERE status = :status
                AND created_at >= :since';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => 'failed',
            ':since'  => date('Y-m-d H:i:s', strtotime('-24 hours')),
        ]);

        return (int)$sth->fetchColumn();
    }

    /**
     * Count guardrail blocks in the last 24 hours.
     *
     * @return int
     */
    public function countGuardrailBlocksLast24h(): int
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*)
                FROM research_runs
                WHERE guardrail_status = :status
                AND created_at >= :since';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => ResearchRunModel::STATUS_BLOCKED,
            ':since'  => date('Y-m-d H:i:s', strtotime('-24 hours')),
        ]);

        return (int)$sth->fetchColumn();
    }
}
