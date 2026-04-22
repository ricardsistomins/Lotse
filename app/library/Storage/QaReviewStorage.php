<?php

namespace app\Storage;

use app\Model\{
    QaReviewModel,
    ReportModel,
    ResearchRunModel
};

class QaReviewStorage extends AbstractStorage
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
    protected string $table = 'qa_reviews';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'                  => 'id',
        'report_revision_id'  => 'reportRevisionId',
        'assigned_user_id'    => 'assignedUserId',
        'reviewed_by_user_id' => 'reviewedByUserId',
        'decision_status'     => 'decisionStatus',
        'decision_notes'      => 'decisionNotes',
        'requested_at'        => 'requestedAt',
        'decided_at'          => 'decidedAt',
        'created_at'          => 'createdAt',
        'updated_at'          => 'updatedAt'
    );
 
    /**                                                   
     * Create a pending QA review record for a revision.
     *                                                                        
     * @param int $reportRevisionId
     * @return int                                                            
     */                                                   
    public function create(int $reportRevisionId): int
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO qa_reviews (report_revision_id, decision_status, requested_at)                                                                 
                VALUES (:reportRevisionId, :decisionStatus, :requestedAt)';

        $sth = $pdo->prepare($sql);
        $sth->execute([                                                       
            ':reportRevisionId' => $reportRevisionId,     
            ':decisionStatus'   => QaReviewModel::STATUS_PENDING,
            ':requestedAt'      => date('Y-m-d H:i:s'),
        ]);                                                                   

        return (int)$pdo->lastInsertId();                                     
    }                                                     

    /**
     * Fetch the QA review record for a given revision.
     *
     * @param int $reportRevisionId
     * @return QaReviewModel|null
     */
    public function getByRevisionId(int $reportRevisionId): ?QaReviewModel
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM qa_reviews
                WHERE report_revision_id = :reportRevisionId
                LIMIT 1';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':reportRevisionId' => $reportRevisionId
        ]);

        return $sth->fetchObject(QaReviewModel::class) ?: null;
    }

    /**
     * Record a QA decision on a review.
     *                                                                        
     * @param int $qaReviewId
     * @param string $decisionStatus                                          
     * @param int $reviewedByUserId                                           
     * @param string|null $decisionNotes                                      
     * @return void                                                           
     */                                                                       
    public function decide(int $qaReviewId, string $decisionStatus, int $reviewedByUserId, ?string $decisionNotes = null): void                       
    {                                                     
        $pdo = $this->getPdo();                                               

        $sql = 'UPDATE qa_reviews                                             
                SET decision_status = :decisionStatus, reviewed_by_user_id = :reviewedByUserId,                                                            
                    decision_notes = :decisionNotes, decided_at = :decidedAt
                WHERE id = :id';                                              

        $sth = $pdo->prepare($sql);                                           
        $sth->execute([                                                       
            ':decisionStatus'    => $decisionStatus,                          
            ':reviewedByUserId'  => $reviewedByUserId,                        
            ':decisionNotes'     => $decisionNotes,                           
            ':decidedAt'         => date('Y-m-d H:i:s'),                      
            ':id'                => $qaReviewId,                              
        ]);                                                                   
    }                
    
    /**                                                                                                                                           
     * Get counts of reports in the QA queue by guardrail status.                                                                                 
     *                                                                                                                                            
     * @return array                                              
     */                                                                                                                                           
    public function getQueueStatusCounts(): array                                                                                                 
    {                                       
        $pdo = $this->getPdo();                                                                                                                   

        $sql = 'SELECT                                                                                                                            
                    COUNT(*)                               AS total,                                                                              
                    SUM(reports.status = :needs_qa)        AS awaiting_review,                                                                    
                    SUM(rr.guardrail_status = :pass)       AS guardrail_pass,
                    SUM(rr.guardrail_status = :review)     AS guardrail_review                                                                    
                FROM reports                    
                JOIN research_runs rr ON rr.id = reports.run_id                                                                                   
                WHERE reports.status = :needs_qa';

        $sth = $pdo->prepare($sql);             
        $sth->execute([                                                                                                                           
            ':needs_qa' => ReportModel::STATUS_NEEDS_QA,
            ':pass'     => ResearchRunModel::STATUS_PASS,                                                                                         
            ':review'   => ResearchRunModel::STATUS_REVIEW,
        ]);                                                                                                                                       

        $row = $sth->fetch();

        return [
            'total'            => $row['total'] ?? 0,
            'awaiting_review'  => $row['awaiting_review'] ?? 0,
            'guardrail_pass'   => $row['guardrail_pass'] ?? 0,
            'guardrail_review' => $row['guardrail_review'] ?? 0,
        ];                                                                                                                                        
    }
}
