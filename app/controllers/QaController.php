<?php

namespace app\controllers;

use app\Storage\ {
    ReportStorage,
    ReportRevisionStorage,
    QaReviewStorage    
};
use app\Model\{
    QaReviewModel,
    ReportModel,
    UserModel
};
use app\Service\AuditService;   

class QaController extends BaseController
{
    /**
     * List last 50 reports in the QA queue.                                      
     */
    public function indexAction(): void                                       
    {                          
        $request = $this->request;
        
        $status = $request->getQuery('status', 'string') ?: null;
        $guardrail = $request->getQuery('guardrail', 'string') ?: null;
        $page = max(1, $request->getQuery('page', 'int', 1));
        
        $reportStorage = new ReportStorage();
        $total = $reportStorage->getQueueItemsCount($status, $guardrail);
        $totalPages = max(1, ceil($total / ReportStorage::PER_PAGE));
        $page = min($page, $totalPages);
        
        $this->view->setVars([
            'queue'           => $reportStorage->getQueueItems($status, $guardrail, $page),
            'getStatusCounts' => (new QaReviewStorage())->getQueueStatusCounts(),
            'filterStatus'    => $status,
            'filterGuardrail' => $guardrail,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'total'           => $total
        ]);
    }                                                                         

    /**                                                                       
     * Approve a report revision.
     *                                                                        
     * @param int $revisionId                             
     */                                                                       
    public function approveAction(): void
    {
        $revisionId = (int)$this->dispatcher->getParam('revisionId');
        $this->handleDecision($revisionId, QaReviewModel::STATUS_APPROVED);
    }                                                                         

    /**
     * Reject a report revision.
     *                                                                        
     * @param int $revisionId
     */                                                                       
    public function rejectAction(): void
    {
        $revisionId = (int)$this->dispatcher->getParam('revisionId');
        $this->handleDecision($revisionId, QaReviewModel::STATUS_REJECTED);                       
    }                                                                         

    /**                                                                       
     * Process an approve or reject decision for a revision.
     *                                                                        
     * @param int $revisionId                          
     * @param string $decision                                                
     */                                                                       
    private function handleDecision(int $revisionId, string $decision): void  
    {                                                                         
        $userId = (int)$this->session->get('userId');     
        $role   = $this->session->get('userRole');                            

        if (!in_array($role, [UserModel::ROLE_ADMIN, UserModel::ROLE_QA])) {                              
            $this->langRedirect('/qa');                                  
            return;                                                           
        }                                                                     
                       
        $revision = (new ReportRevisionStorage())->getById($revisionId);

        if (!$revision) {                                                     
            $this->langRedirect('/qa');                                 
            return;                                       
        }                                                                     

        $qaReviewStorage = new QaReviewStorage();                             
        $qaReview = $qaReviewStorage->getByRevisionId($revisionId);

        if (!$qaReview || $qaReview->decisionStatus !== QaReviewModel::STATUS_PENDING) {
            $this->langRedirect('/qa');
            return;
        }

        $qaReviewStorage->decide($qaReview->id, $decision, $userId);

        $reportStatus  = $decision === QaReviewModel::STATUS_APPROVED ? ReportModel::STATUS_APPROVED : ReportModel::STATUS_REJECTED;
        $reportStorage = new ReportStorage();
        $reportStorage->updateStatus($revision->reportId, $reportStatus, $userId);

        if ($decision === QaReviewModel::STATUS_APPROVED) {
            $reportStorage->setApprovedRevision($revision->reportId, $revisionId);
        }

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,
            action:      'report.' . $decision,
            entityType:  'report_revision',
            entityId:    $revisionId,
            metadata:    [
                'report_id' => $revision->reportId, 
                'qa_review_id' => $qaReview->id
            ]
        );                                                

        $this->langRedirect('/qa');                 
    }
}

