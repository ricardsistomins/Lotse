<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;

use app\Storage\ {
    ReportStorage,
    ReportRevisionStorage,
    QaReviewStorage    
};

use app\Service\AuditService;   

class QaController extends Controller
{
    /**
     * List last 50 reports in the QA queue.                                      
     */
    public function indexAction(): void                                       
    {                                                                         
        $this->view->setVar('queue', (new ReportStorage())->getQueueItems());
    }                                                                         

    /**                                                                       
     * Approve a report revision.
     *                                                                        
     * @param int $revisionId                             
     */                                                                       
    public function approveAction(int $revisionId): void  
    {                                                                         
        $this->handleDecision($revisionId, 'approved');
    }                                                                         

    /**
     * Reject a report revision.
     *                                                                        
     * @param int $revisionId
     */                                                                       
    public function rejectAction(int $revisionId): void                       
    {                                                                         
        $this->handleDecision($revisionId, 'rejected');                       
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

        if (!in_array($role, ['admin', 'qa'])) {                              
            $this->response->redirect('/qa');                                 
            $this->response->send();  
            
            return;                                                           
        }                                                                     
                       
        $revision = (new ReportRevisionStorage())->getById($revisionId);

        if (!$revision) {                                                     
            $this->response->redirect('/qa');                                 
            $this->response->send();       
            
            return;                                       
        }                                                                     

        $qaReviewStorage = new QaReviewStorage();                             
        $qaReview = $qaReviewStorage->getByRevisionId($revisionId);

        if (!$qaReview || $qaReview->decisionStatus !== 'pending') {
            $this->response->redirect('/qa');
            $this->response->send();

            return;
        }

        $qaReviewStorage->decide($qaReview->id, $decision, $userId);
        $reportStorage = new ReportStorage();
        $reportStorage->updateStatus($revision->reportId, $decision, $userId);

        if ($decision === 'approved') {
            $reportStorage->setApprovedRevision($revision->reportId, $revisionId);
        }

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,
            action:      'report.' . $decision,
            entityType:  'report_revision',
            entityId:    $revisionId,
            metadata:    ['report_id' => $revision->reportId, 'qa_review_id' => $qaReview->id]
        );                                                

        $this->response->redirect('/qa');                 
        $this->response->send();
    }
}

