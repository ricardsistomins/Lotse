<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;                                                                  
use app\Storage\ReportStorage;
use app\Storage\ReportRevisionStorage;                                                       
use app\Service\AuditService;

class ReportController extends Controller                                                    
{                                                                                            
    private ReportStorage         $reportStorage;                                            
    private ReportRevisionStorage $revisionStorage;                                          
    private AuditService          $auditService;

    public function onConstruct(): void
    {                                                                                        
        $this->reportStorage   = new ReportStorage();
        $this->revisionStorage = new ReportRevisionStorage();                                
        $this->auditService    = new AuditService($this->db);                                
    }                                                                                        

    /**
     * List all reports
     */
    public function indexAction() 
    {
        $this->view->reports = $this->reportStorage->getAll();
    }
    
    /**         
     * Show report editor with current content and revision history.
     */                                                                                      
    public function viewAction(int $id): void
    {                                                                                        
        $response = $this->response;
        $view = $this->view;
        $session = $this->session;
        
        $report = $this->reportStorage->getById($id);

        if (!$report) {
            $response->redirect('/dashboard');                                         
            $response->send();                                                         
            return;
        }                                                                                    

        $content   = $this->revisionStorage->getLatestContent($id);                          
        $revisions = $this->revisionStorage->getAllByReportId($id);
        $role      = $session->get('userRole', 'string');                              
        $canAct    = in_array($role, ['admin', 'qa']);
        
        $view->report    = $report;                                                    
        $view->content   = $content;                                                   
        $view->revisions = $revisions;                                                 
        $view->canAct    = $canAct;
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

        $revisionId = $this->revisionStorage->save($id, $content, $userId);                  
        $this->reportStorage->setCurrentRevision($id, $revisionId);                          

        $this->auditService->log(                                                            
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

        $this->reportStorage->updateStatus($id, $status, $userId);

        $action = $status === 'approved' ? 'report.approved' : 'report.rejected';            

        $this->auditService->log(                                                            
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
