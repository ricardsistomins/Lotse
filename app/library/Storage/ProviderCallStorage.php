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
                    (:run_id, :provider_kind, :provider_name, :request_purpose, :status,
                    :latency_ms, :input_tokens, :output_tokens, :error_code, :error_message,
                    :fallback_used, :finished_at)';

        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':run_id'          => $runId,
            ':provider_kind'   => $providerKind,
            ':provider_name'   => $providerName,
            ':request_purpose' => $requestPurpose,
            ':status'          => $status,
            ':latency_ms'      => $latencyMs,
            ':input_tokens'    => $inputTokens,
            ':output_tokens'   => $outputTokens,
            ':error_code'      => $errorCode,
            ':error_message'   => $errorMessage,
            ':fallback_used'   => (int)$fallbackUsed ?? 0,
            ':finished_at'     => date('Y-m-d H:i:s'),
        ]);    
    }
}
