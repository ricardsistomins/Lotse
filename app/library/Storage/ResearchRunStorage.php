<?php

namespace app\Storage;

class ResearchRunStorage extends AbstractStorage
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
    protected string $table = 'research_runs';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'                    => 'id',
        'run_type'              => 'runType',
        'trigger_source'        => 'triggerSource',
        'idempotency_key'       => 'idempotencyKey',
        'canonical_scope_key'   => 'canonicalScopeKey',
        'customer_id'           => 'customerId',
        'report_id'             => 'reportId',
        'parent_run_id'         => 'parentRunId',
        'provider_profile_name' => 'providerProfileName',
        'llm_provider_name'     => 'llmProviderName',
        'search_provider_name'  => 'searchProviderName',
        'status'                => 'status',
        'guardrail_status'      => 'guardrailStatus',
        'retry_count'           => 'retryCount',
        'error_summary'         => 'errorSummary',
        'created_by_user_id'    => 'createdByUserId',
        'started_at'            => 'startedAt',
        'finished_at'           => 'finishedAt',
        'created_at'            => 'createdAt',
        'updated_at'            => 'updatedAt'
    );
 
    /**
     * Create a new research run row and return its ID.
     * 
     * @param string $runType
     * @param string $triggerSource
     * @param string $idempotencyKey
     * @param string $canonicalScopeKey
     * @param string $providerProfileName
     * @param string $llmProviderName
     * @param string|null $searchProviderName
     * @param int|null $customerId
     * @param int|null $createdByUserId
     * @return int
     */
    public function create(string $runType, string $triggerSource, string $idempotencyKey, string $canonicalScopeKey, string $providerProfileName, string $llmProviderName, ?string $searchProviderName = null, ?int $customerId = null, ?int $createdByUserId = null): int 
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO research_runs (
                    run_type, trigger_source, idempotency_key, canonical_scope_key,
                    provider_profile_name, llm_provider_name, search_provider_name,
                    customer_id, created_by_user_id, status, started_at
                )
                VALUES (
                    :run_type, :trigger_source, :idempotency_key, :canonical_scope_key,
                    :provider_profile_name, :llm_provider_name, :search_provider_name,
                    :customer_id, :created_by_user_id, :status, :started_at
                )';
        
        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':run_type'              => $runType,
            ':trigger_source'        => $triggerSource,
            ':idempotency_key'       => $idempotencyKey,
            ':canonical_scope_key'   => $canonicalScopeKey,
            ':provider_profile_name' => $providerProfileName,
            ':llm_provider_name'     => $llmProviderName,
            ':search_provider_name'  => $searchProviderName,
            ':customer_id'           => $customerId,
            ':created_by_user_id'    => $createdByUserId,
            ':status'                => 'running',
            ':started_at'            => date('Y-m-d H:i:s'),
        ]);

        return (int)$pdo->lastInsertId();
   }

    /**
     * Update run status and finished timestamp.
     * 
     * @param int $runId
     * @param string $status
     * @param string|null $errorSummary
     * @param string $guardrailStatus
     * @return void
     */
    public function finish(int $runId, string $status, ?string $errorSummary = null, string $guardrailStatus = 'pending'): void
    {
        $pdo = $this->getPdo();

        $sql = 'UPDATE research_runs
                SET status = :status, guardrail_status = :guardrailStatus, error_summary = :errorSummary, finished_at = :finishedAt
                WHERE id = :id';
        
        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':status'          => $status,
            ':guardrailStatus' => $guardrailStatus,
            ':errorSummary'    => $errorSummary,
            ':finishedAt'      => date('Y-m-d H:i:s'),
            ':id'              => $runId,
        ]);
    }
}
