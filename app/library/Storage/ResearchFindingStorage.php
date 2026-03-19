<?php

namespace app\Storage;

class ResearchFindingStorage extends AbstractStorage
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
    protected string $table = 'research_findings';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'                      => 'id',
        'run_id'                  => 'runId',
        'finding_key'             => 'findingKey',
        'finding_type'            => 'findingType',
        'title'                   => 'title',
        'normalized_payload'      => 'normalizedPayload',
        'source_count'            => 'sourceCount',
        'official_source_present' => 'officialSourcePresent',
        'confidence_score'        => 'confidenceScore',
        'risk_flags'              => 'riskFlags',
        'dedupe_hash'             => 'dedupeHash',
        'status'                  => 'status',
        'created_at'              => 'createdAt',
        'updated_at'              => 'updatedAt'
    );
 
    /**
     * Save a single finding extracted by the LLM during a run.
     * 
     * @param int $runId
     * @param string $findingKey
     * @param string $findingType
     * @param string $title
     * @param array $normalizedPayload
     * @param string $dedupeHash
     * @param int $sourceCount
     * @param bool $officialSourcePresent
     * @param float $confidenceScore
     * @param array|null $riskFlags
     * @return int
     */
    public function save(int $runId, string $findingKey, string $findingType, string $title, array $normalizedPayload, string $dedupeHash, int $sourceCount = 0, bool $officialSourcePresent = false, float $confidenceScore = 0.0, ?array $riskFlags = null): int 
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO research_findings (
                    run_id, finding_key, finding_type, title, normalized_payload,
                    source_count, official_source_present, confidence_score,
                    risk_flags, dedupe_hash, status
                )
                VALUES (
                    :run_id, :finding_key, :finding_type, :title, :normalized_payload,
                    :source_count, :official_source_present, :confidence_score,
                    :risk_flags, :dedupe_hash, :status
                )';
        
        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':run_id'                  => $runId,
            ':finding_key'             => $findingKey,
            ':finding_type'            => $findingType,
            ':title'                   => $title,
            ':normalized_payload'      => json_encode($normalizedPayload),
            ':source_count'            => $sourceCount,
            ':official_source_present' => (int)$officialSourcePresent,
            ':confidence_score'        => $confidenceScore,
            ':risk_flags'              => $riskFlags !== null ? json_encode($riskFlags) : null,
            ':dedupe_hash'             => $dedupeHash,
            ':status'                  => 'extracted',
        ]);

        return (int)$pdo->lastInsertId();
    }
    
    /**                                                                           
     * Count all research findings for a run                                      
     *                                                                            
     * @param int $runId                                                          
     * @return int                                                                
     */             
    public function countByRunId(int $runId): int                                 
    {               
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*)
                FROM research_findings 
                WHERE run_id = :run_id';   

        $sth = $pdo->prepare($sql);                                               
        $sth->execute([
            ':run_id' => $runId                                                   
        ]);         

        return (int)$sth->fetchColumn();                                         
    }
}
