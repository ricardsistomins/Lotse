<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;
use app\Storage\ {
    ReportStorage,
    DashboardStorage
};


class DashboardController extends Controller
{
    public function indexAction() 
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
            'admin' => 'dashboard_admin',
            'dev'   => 'dashboard_dev',
            'qa'    => 'dashboard_qa',
            default => 'dashboard_admin'
        };
        
        $runId = $this->orchestrator->run($triggerSource, $query, $userId, $this->db);
                       
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
