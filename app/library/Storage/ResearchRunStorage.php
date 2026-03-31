<?php

namespace app\Storage;

use app\Model\ResearchRunModel;

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
        'query'                 => 'query',    
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
        'block_reason'          => 'blockReason',
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
     * @param string|null $query
     * @param string|null $searchProviderName
     * @param int|null $customerId
     * @param int|null $createdByUserId
     * @return int
     */
    public function create(string $runType, string $triggerSource, string $idempotencyKey, string $canonicalScopeKey, string $providerProfileName, string $llmProviderName, ?string $query = null, ?string $searchProviderName = null, ?int $customerId = null, ?int $createdByUserId = null): int
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO research_runs (
                    run_type, trigger_source, idempotency_key, canonical_scope_key,
                    query, provider_profile_name, llm_provider_name, search_provider_name,
                    customer_id, created_by_user_id, status, started_at
                )
                VALUES (
                    :runType, :triggerSource, :idempotencyKey, :canonicalScopeKey,
                    :query, :providerProfileName, :llmProviderName, :searchProviderName,
                    :customerId, :createdByUserId, :status, :startedAt
                )';

        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':runType'             => $runType,
            ':triggerSource'       => $triggerSource,
            ':idempotencyKey'      => $idempotencyKey,
            ':canonicalScopeKey'   => $canonicalScopeKey,
            ':query'               => $query,
            ':providerProfileName' => $providerProfileName,
            ':llmProviderName'     => $llmProviderName,
            ':searchProviderName'  => $searchProviderName,
            ':customerId'          => $customerId,
            ':createdByUserId'     => $createdByUserId,
            ':status'              => ResearchRunModel::STATUS_RUNNING,
            ':startedAt'           => date('Y-m-d H:i:s'),
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Find a run by its idempotency key.                                            
     *                                                                               
     * @param string $idempotencyKey                                                 
     * @return ResearchRunModel|null                                                 
     */                                                                              
    public function getByIdempotencyKey(string $idempotencyKey): ?ResearchRunModel   
    {                                                                                
        $pdo = $this->getPdo();                                                      
                                                                                     
        $sql = 'SELECT ' . $this->mapFields() . '                                    
                FROM research_runs                                                   
                WHERE idempotency_key = :idempotencyKey                              
                LIMIT 1';
                                                                                     
        $sth = $pdo->prepare($sql);                                                  
        $sth->execute([
            ':idempotencyKey' => $idempotencyKey
        ]);                       
                                                                                     
        return $sth->fetchObject(ResearchRunModel::class) ?: null;                   
    }                
    
    /**
     * Get run by canonical_scope_key and status
     * 
     * @param string $canonicalScopeKey
     * @return ResearchRunModel|null
     */
    public function getRunningByCanonicalScopeKey(string $canonicalScopeKey): ?ResearchRunModel
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM research_runs
                WHERE canonical_scope_key = :canonicalScopeKey
                AND status = :status
                LIMIT 1';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':canonicalScopeKey' => $canonicalScopeKey,
            ':status'            => ResearchRunModel::STATUS_RUNNING
        ]);
        
        return $sth->fetchObject(ResearchRunModel::class) ?: null;
    }
    
    /**
     * Update run status and finished timestamp.
     *
     * @param int $runId
     * @param string $status
     * @param string|null $errorSummary
     * @param string|null $blockReason
     * @param string $guardrailStatus
     * @return void
     */
    public function finish(int $runId, string $status, ?string $errorSummary = null, ?string $blockReason = null, string $guardrailStatus = ResearchRunModel::STATUS_PENDING): void
    {
        $pdo = $this->getPdo();

        $sql = 'UPDATE research_runs
                SET status = :status, guardrail_status = :guardrailStatus, 
                error_summary = :errorSummary, block_reason = :blockReason, finished_at = :finishedAt
                WHERE id = :id';

        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':status'          => $status,
            ':guardrailStatus' => $guardrailStatus,
            ':errorSummary'    => $errorSummary,
            ':blockReason'     => $blockReason,
            ':finishedAt'      => date('Y-m-d H:i:s'),
            ':id'              => $runId,
        ]);
    }
    
    /**
     * Increments run retry count
     * 
     * @param int $runId
     * @return void
     */
    public function incrementRetryCount(int $runId): void
    {
        $pdo = $this->getPdo();
        
        $sql = 'UPDATE research_runs
                SET retry_count = retry_count + 1, status = :status, finished_at = NULL, updated_at = :updatedAt
                WHERE id = :id';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':status'    => ResearchRunModel::STATUS_RUNNING,
            ':updatedAt' => date('Y-m-d H:i:s'),
            ':id'        => $runId
        ]); 
    }
    
    /**
     * Fetch a single run by ID.
     *
     * @param int $runId
     * @return ResearchRunModel|null
     */
    public function getById(int $runId): ?ResearchRunModel
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM research_runs
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([':id' => $runId]);

        return $sth->fetchObject(ResearchRunModel::class) ?: null;
    }

    /**
     * Fetch all runs, newest first - with optional filters
     *
     * @param string|null $status
     * @param string|null $triggerSource
     * @return ResearchRunModel[]
     */
    public function getAll(?string $status = null, ?string $triggerSource = null): array
    {
        $pdo = $this->getPdo();

        $where = [];
        $params = [];
        
        if ($status !== null) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        
        if ($triggerSource !== null) {
            $where[] = 'trigger_source = :triggerSource';
            $params[':triggerSource'] = $triggerSource;
        }

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM research_runs ' .
                ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
                ORDER BY id DESC
                LIMIT 100';

        $sth = $pdo->prepare($sql);
        $sth->execute($params);

        return $sth->fetchAll($pdo::FETCH_CLASS, ResearchRunModel::class);
    }
}
