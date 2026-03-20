<?php

namespace app\Storage;

class ProviderCallStorage extends AbstractStorage
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
    protected string $table = 'provider_calls';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'                 => 'id',
        'run_id'             => 'runId',
        'provider_kind'      => 'providerKind',
        'provider_name'      => 'providerName',
        'request_purpose'    => 'requestPurpose',
        'request_hash'       => 'requestHash',
        'status'             => 'status',
        'input_tokens'       => 'inputTokens',
        'output_tokens'      => 'outputTokens',
        'latency_ms'         => 'latencyMs',
        'estimated_cost_usd' => 'estimatedCostUsd',
        'error_code'         => 'errorCode',
        'error_message'      => 'errorMessage',
        'fallback_used'      => 'fallbackUsed',
        'created_at'         => 'createdAt',
        'finished_at'        => 'finishedAt',
    );
 
    /**
     * Log a completed provider call to the database.
     * 
     * @param string $providerKind
     * @param string $providerName
     * @param string $requestPurpose
     * @param string $status
     * @param int $latencyMs
     * @param int|null $runId
     * @param int|null $inputTokens
     * @param int|null $outputTokens
     * @param string|null $errorCode
     * @param string|null $errorMessage
     * @param bool $fallbackUsed
     * @return void
     */
    public function log(string $providerKind, string $providerName, string $requestPurpose, string $status, int $latencyMs, ?int $runId = null, ?int $inputTokens = null, ?int $outputTokens = null, ?string $errorCode = null, ?string $errorMessage = null, bool $fallbackUsed = false): void 
    {
        $pdo = $this->getPdo();
      
        $sql = 'INSERT INTO provider_calls
                    (run_id, provider_kind, provider_name, request_purpose, status,
                    latency_ms, input_tokens, output_tokens, error_code, error_message,
                    fallback_used, finished_at)
                VALUES
                    (:runId, :providerKind, :providerName, :requestPurpose, :status,
                    :latencyMs, :inputTokens, :outputTokens, :errorCode, :errorMessage,
                    :fallbackUsed, :finishedAt)';

        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':runId'          => $runId,
            ':providerKind'   => $providerKind,
            ':providerName'   => $providerName,
            ':requestPurpose' => $requestPurpose,
            ':status'         => $status,
            ':latencyMs'      => $latencyMs,
            ':inputTokens'    => $inputTokens,
            ':outputTokens'   => $outputTokens,
            ':errorCode'      => $errorCode,
            ':errorMessage'   => $errorMessage,
            ':fallbackUsed'   => (int)$fallbackUsed ?? 0,
            ':finishedAt'     => date('Y-m-d H:i:s'),
        ]);    
    }
    
   /**                                                                           
    * Fetch all provider calls for a run                                      
    *
    * @param int $runId
    * @return array
    */
    public function getAllByRunId(int $runId): array
    {                                                                             
        $pdo = $this->getPdo();

        $sql = 'SELECT * 
                FROM provider_calls 
                WHERE run_id = :runId 
                ORDER BY id ASC';                                                                         

        $sth = $pdo->prepare($sql);                                               
        $sth->execute([
            ':runId' => $runId                                                    
        ]);                                                                       

        return $sth->fetchAll($pdo::FETCH_ASSOC);                                 
    }              
}
