<?php

namespace app\Storage;

use app\Model\{
    CustomerModel,
    ResearchRunModel,
    ReportModel,
    ProviderCallModel
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
     * Return now() - 24h
     * 
     * @return string
     */
    private function since24h(): string 
    {
//        CHANGE BACK TO 24H
        return date('Y-m-d H:i:s', strtotime('-1 month')); 
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
            ':status' => ProviderCallModel::STATUS_FAILED,
            ':since'  => $this->since24h(),
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
            ':since'  => $this->since24h(),
        ]);

        return (int)$sth->fetchColumn();
    }
    
    /**
     * Get provider call failures in the last 24h
     * 
     * @return \stdClass[]
     */
    public function getProviderFailuresLast24h(): array
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT pc.id, pc.run_id, pc.provider_name,
                       pc.request_purpose, pc.error_code,
                       pc.error_message, pc.created_at
                FROM provider_calls pc
                WHERE pc.status = :status
                AND pc.created_at >= :since
                ORDER BY pc.created_at DESC
                LIMIT 10';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => ProviderCallModel::STATUS_FAILED,
            ':since'  => $this->since24h()
        ]);
        
        return $sth->fetchAll($pdo::FETCH_OBJ);
    }
    
    /**
     * Get guardrail-blocked runs in the last 24h
     * 
     * @return \stdClass[]
     */
    public function getGuardrailBlocksLast24h(): array
    {
        $pdo = $this->getPdo();
        
        $sql = 'SELECT rr.id, rr.query, rr.block_reason, 
                       rr.created_at, c.company_name
                FROM research_runs rr
                LEFT JOIN customers c ON c.id = rr.customer_id
                WHERE rr.guardrail_status = :status
                AND rr.created_at >= :since
                ORDER BY rr.created_at DESC
                LIMIT 10';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status' => ResearchRunModel::STATUS_BLOCKED,
            ':since'  => $this->since24h()
        ]);
        
        return $sth->fetchAll($pdo::FETCH_OBJ);
    }
}
