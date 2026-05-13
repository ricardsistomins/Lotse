<?php

namespace app\Storage;

use app\Model\ResearchSourceModel;

class ResearchSourceStorage extends AbstractStorage
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
    protected string $table = 'research_sources';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'               => 'id',
        'run_id'           => 'runId',
        'source_url'       => 'sourceUrl',
        'source_domain'    => 'sourceDomain',
        'source_title'     => 'sourceTitle',
        'source_type'      => 'sourceType',
        'source_text'      => 'sourceText',
        'provider_name'    => 'providerName',
        'http_status'      => 'httpStatus',
        'content_hash'     => 'contentHash',
        'captured_excerpt' => 'capturedExcerpt',
        'raw_payload'      => 'rawPayload',
        'is_official'      => 'isOfficial',
        'read_failed'      => 'readFailed',
        'retrieved_at'     => 'retrievedAt',
        'created_at'       => 'createdAt',
        'updated_at'       => 'updatedAt',
    );
 
    /**
     * Save a single source collected during a run.
     * 
     * @param int $runId
     * @param string $sourceUrl
     * @param string $sourceDomain
     * @param string $sourceType
     * @param string $retrievedAt
     * @param string|null $sourceTitle
     * @param string|null $providerName
     * @param string|null $capturedExcerpt
     * @param bool $isOfficial
     * @param string|null $sourceText
     * @param bool $readFailed
     * @return int
     */
    public function save(int $runId, string $sourceUrl, string $sourceDomain, string $sourceType, string $retrievedAt, ?string $sourceTitle = null, ?string $providerName = null, ?string $capturedExcerpt = null, bool $isOfficial = false, ?string $sourceText = null, bool $readFailed = false): int 
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO research_sources (
                    run_id, source_url, source_domain, source_title, source_type,
                    source_text, provider_name, captured_excerpt, is_official,
                    read_failed, retrieved_at
                )
                VALUES (
                    :runId, :sourceUrl, :sourceDomain, :sourceTitle, :sourceType,
                    :sourceText, :providerName, :capturedExcerpt, :isOfficial, 
                    :readFailed, :retrievedAt
                )';

        $sth = $pdo->prepare($sql);

        $sth->execute([
            ':runId'           => $runId,
            ':sourceUrl'       => $sourceUrl,
            ':sourceDomain'    => $sourceDomain,
            ':sourceTitle'     => $sourceTitle,
            ':sourceType'      => $sourceType,
            ':sourceText'      => $sourceText,
            ':providerName'    => $providerName,
            ':capturedExcerpt' => $capturedExcerpt,
            ':isOfficial'      => (int)$isOfficial,
            ':readFailed'      => (int)$readFailed,
            ':retrievedAt'     => $retrievedAt,
        ]);

        return (int)$pdo->lastInsertId();
    }
    
    /**
     * Count all research sources
     * 
     * @param int $runId
     * @return int
     */
    public function countByRunId(int $runId): int 
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT COUNT(*)
                FROM research_sources
                WHERE run_id = :runId';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':runId' => $runId
        ]);

        return (int)$sth->fetchColumn();
    }
    
    /**
     * Fetch all sources for a run.
     *
     * @param int $runId
     * @return ResearchSourceModel[]
     */
    public function getAllByRunId(int $runId): array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM research_sources
                WHERE run_id = :runId
                ORDER BY id ASC';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':runId' => $runId
        ]);

        return $sth->fetchAll($pdo::FETCH_CLASS, ResearchSourceModel::class);
    }   
    
    /**
     * Set is_official flag on a source by run + url
     * 
     * @param int $runId
     * @param string $sourceUrl
     * @param bool $isOfficial
     * @return void
     */
    public function setIsOfficial(int $runId, string $sourceUrl, bool $isOfficial): void
    {
        $pdo = $this->getPdo();
        
        $sql = 'UPDATE research_sources
                SET is_official = :isOfficial
                WHERE run_id = :runId
                AND source_url = :sourceUrl';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':isOfficial' => (int)$isOfficial,
            ':runId'      => $runId,
            ':sourceUrl'  => $sourceUrl
        ]);
    }
}
