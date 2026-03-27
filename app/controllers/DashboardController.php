<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;
use app\Service\DuplicateRunException;
use app\Storage\ {
    ReportStorage,
    DashboardStorage
};
use app\Model\UserModel;

class DashboardController extends Controller
{
    public function indexAction(): void
    {
       $dashboardStorage = new DashboardStorage();

        $this->view->setVars([
            'activeCustomers'       => $dashboardStorage->countActiveCustomers(),
            'runsInProgress'        => $dashboardStorage->countRunsInProgress(),
            'blockedRuns'           => $dashboardStorage->countBlockedRuns(),
            'reportsAwaitingQa'     => $dashboardStorage->countReportsAwaitingQa(),
            'approvedReports'       => $dashboardStorage->countApprovedReports(),
            'providerFailures24h'   => $dashboardStorage->countProviderFailuresLast24h(),
            'guardrailBlocks24h'    => $dashboardStorage->countGuardrailBlocksLast24h()
        ]);
    }
    
    /**
     * Handle manual research run trigger from the dashboard.
     */
    public function triggerAction() 
    {
        $request = $this->request;
        $response = $this->response;
        $session = $this->session;
        
        if (!$request->isPost()) {
            $response->redirect('/dashboard');
            $response->send();
            
            return;
        }
        
        $query = $request->getPost('query', 'string');
        $userId = $session->get('userId', 'int');
        $role = $session->get('userRole', 'string');
        
        $triggerSource = match($role) {
            UserModel::ROLE_ADMIN => 'dashboard_admin',                                                
            UserModel::ROLE_DEV   => 'dashboard_dev',
            UserModel::ROLE_QA    => 'dashboard_qa',  
            default => 'dashboard_admin'
        };
        
        try {                                                                                    
            $runId = $this->orchestrator->run($triggerSource, $query, $userId, $this->db);
        } catch (DuplicateRunException $e) {                                                     
            $response->redirect('/run/' . $e->existingRunId . '?retrigger=1');
            $response->send();
      
            return;                                                                              
        }        
                       
        $report = (new ReportStorage())->getByRunId($runId);
        $reportId = $report ? $report->id : null;                                                  

        $url = '/dashboard?runId=' . $runId;                                                         
        
        if ($reportId) {                                                                             
            $url .= '&reportId=' . $reportId;                                                        
        }  
        
        $response->redirect($url);
        $response->send();
    }
}
