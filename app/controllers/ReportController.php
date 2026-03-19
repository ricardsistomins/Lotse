<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;                                                                  
use app\Storage\ {
    ReportStorage,
    ReportRevisionStorage,
    ResearchRunStorage,
    ResearchSourceStorage,
    ResearchFindingStorage
};                                           
use app\Service\AuditService;

class ReportController extends Controller                                                    
{                                                                                    
    /**
     * List all reports
     */
    public function indexAction() 
    {
        $this->view->setVar('reports', (new ReportStorage())->getAll());
    }
    
    /**         
     * Show report editor with current content and revision history.
     */                                                                                      
    public function viewAction(int $id): void
    {                                                                                        
        $response = $this->response;
        $view = $this->view;
        $session = $this->session;
        
        $report = (new ReportStorage())->getById($id);

        if (!$report) {
            $response->redirect('/dashboard');
            $response->send();

            return;
        }

        $run          = (new ResearchRunStorage())->getById($report['run_id']);
        $sourceCount  = (new ResearchSourceStorage())->countByRunId($report['run_id']);
        $findingCount = (new ResearchFindingStorage())->countByRunId($report['run_id']);

        $revisionStorage = new ReportRevisionStorage();
        $content   = $revisionStorage->getLatestContent($id);                          
        $revisions = $revisionStorage->getAllByReportId($id);
        
        $role      = $session->get('userRole', 'string');                              
        $canAct    = in_array($role, ['admin', 'qa']);
        
        $view->setVars([
            'report'       => $report,
            'revisions'    => $revisions,
            'canAct'       => $canAct,
            'run'          => $run,
            'sourceCount'  => $sourceCount,
            'findingCount' => $findingCount,
            'content'      => $content
        ]);
    }

    /**
     * Save edited content as a new revision.
     */                                                                                      
    public function saveAction(int $id): void
    {          
        $response = $this->response;
        $request = $this->request;
        $session = $this->session;
        
        if (!$request->isPost()) {
            $response->redirect('/report/' . $id);                                     
            $response->send();        
            
            return;                                                                          
        }                                                                                    

        $content = $request->getPost('content', 'string');                             
        $userId  = $session->get('userId', 'int');                                     

        $revisionId = (new ReportRevisionStorage())->save($id, $content, $userId);
        (new ReportStorage())->setCurrentRevision($id, $revisionId);                          

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,
            action:      'report.saved',
            entityType:  'report',
            entityId:    $id,
            metadata:    ['revision_id' => $revisionId]
        );                                                                                   

        $response->redirect('/report/' . $id);                                         
        $response->send();
    }                                                                                        

    /**
     * Approve or reject a report. Admin and QA only.
     */                                                                                      
    public function updateStatusAction(int $id): void
    {          
        $response = $this->response;
        $request = $this->request;
        $session = $this->session;
        
        if (!$request->isPost()) {
            $response->redirect('/report/' . $id);                                     
            $response->send();
            
            return;                                                                          
        }       

        $role = $session->get('userRole', 'string');                                   

        if (!in_array($role, ['admin', 'qa'])) {                                             
            $response->redirect('/report/' . $id);
            $response->send();   
            
            return;                                                                          
        }

        $status = $request->getPost('status', 'string');                               
        $userId = $session->get('userId', 'int');

        if (!in_array($status, ['approved', 'rejected'])) {
            $response->redirect('/report/' . $id);                                     
            $response->send();
            
            return;                                                                          
        }

        (new ReportStorage())->updateStatus($id, $status, $userId);

        $action = $status === 'approved' ? 'report.approved' : 'report.rejected';            

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,
            action:      $action,
            entityType:  'report',
            entityId:    $id,
            metadata:    ['status' => $status]
        );                                                                                   

        $response->redirect('/report/' . $id);
        $response->send();
    }
}                        
