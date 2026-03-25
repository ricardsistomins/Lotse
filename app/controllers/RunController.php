<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;
use app\Service\DuplicateRunException;
use app\Storage\ {
    ResearchRunStorage,
    ProviderCallStorage,
    ResearchSourceStorage,
    ResearchFindingStorage,
    ReportStorage
};

class RunController extends Controller
{
    /**
     * List all research runs
     */
    public function indexAction(): void
    {   
        $request = $this->request;
        
        $status = $request->getQuery('status', 'string') ?: null;
        $triggerSource = $request->getQuery('trigger', 'string') ?: null;
        
        $this->view->setVars([
            'runs'          => (new ResearchRunStorage())->getAll($status, $triggerSource),
            'filterStatus'  => $status,
            'filterTrigger' => $triggerSource
        ]);
    }                                                                          

    /**                                                                       
     * Show full detail for a single run
     */                                                                       
    public function viewAction(int $id): void                              
    {
        $response = $this->response;

        $run = (new ResearchRunStorage())->getById($id);

        if (!$run) {                                                          
            $response->redirect('/runs');
            $response->send();                                                

            return;
        }                                                                     

        $providerCalls = (new ProviderCallStorage())->getAllByRunId($id);     
        $sources       = (new ResearchSourceStorage())->getAllByRunId($id);
        $findings      = (new ResearchFindingStorage())->getAllByRunId($id);  
        $report        = (new ReportStorage())->getByRunId($id);              

        $this->view->setVars([                                                
            'run'           => $run,                                       
            'providerCalls' => $providerCalls,                                
            'sources'       => $sources,                                      
            'findings'      => $findings,                                     
            'report'        => $report,                                       
        ]);                                                                
    }
    
    /**
     * Re-trigger a run using the same query. Admin and dev only.
     * 
     * @param int $id
     * @return void
     */
    public function retriggerAction(int $id): void 
    {
        $response = $this->response;
        $session = $this->session;
        
        $userRole = $session->get('userRole', 'string');

        if (!in_array($userRole, ['admin', 'dev'])) {
            $response->redirect('/run/' . $id);
            $response->send();

            return;
        }

        $run = (new ResearchRunStorage())->getById($id);

        if (!$run || empty($run->query)) {
            $response->redirect('/run/' . $id);
            $response->send();
            
            return;
        }
     
        $userId = $session->get('userId', 'int');
        $triggerSource = match($userRole) {
            'admin'   => 'dashboard_admin',
            'dev'     => 'dashboard_dev',
            default => 'dashboard_admin'
        };
        
        try {
            $newRunId = $this->orchestrator->run($triggerSource, $run->query, $userId, $this->db);
        } catch (DuplicateRunException $e) {
            $response->redirect('/run/' . $e->existingRunId . '?duplicate=1');
            $response->send();

            return;
        }

        $response->redirect('/run/' . $newRunId . '?retrigger=1');
        $response->send();
    }
}
