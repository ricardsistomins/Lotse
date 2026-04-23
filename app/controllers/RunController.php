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
use app\Model\{
    UserModel,
    ResearchRunModel
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
        $page = max(1, $request->getQuery('page', 'int', 1));
        
        $researchRunStorage = new ResearchRunStorage();
        $total = $researchRunStorage->getCount($status, $triggerSource);
        $totalPages = max(1, ceil($total / ResearchRunStorage::PER_PAGE));
        $page = min($page, $totalPages);
        
        $this->view->setVars([
            'runs'           => $researchRunStorage->getRunsByLimit($status, $triggerSource, $page),
            'filterStatus'   => $status,
            'filterTrigger'  => $triggerSource,
            'runStatusCount' => $researchRunStorage->getStatusCounts(),
            'page'           => $page,
            'totalPages'     => $totalPages,
            'total'          => $total
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

        if (!in_array($userRole, [UserModel::ROLE_ADMIN, UserModel::ROLE_DEV])) {
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
            UserModel::ROLE_ADMIN => ResearchRunModel::TRIGGER_DASHBOARD_ADMIN,                                                
            UserModel::ROLE_DEV   => ResearchRunModel::TRIGGER_DASHBOARD_DEV, 
            default               => ResearchRunModel::TRIGGER_DASHBOARD_ADMIN
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
