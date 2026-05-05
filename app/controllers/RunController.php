<?php

namespace app\controllers;

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

class RunController extends BaseController
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
    public function viewAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $run = (new ResearchRunStorage())->getById($id);

        if (!$run) {                                                          
            $this->langRedirect('/runs');                                               
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
    public function retriggerAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $session = $this->session;
        
        $userRole = $session->get('userRole', 'string');

        if (!in_array($userRole, [UserModel::ROLE_ADMIN, UserModel::ROLE_DEV])) {
            $this->langRedirect('/run/' . $id);
            return;
        }

        $run = (new ResearchRunStorage())->getById($id);

        if (!$run || empty($run->query)) {
            $this->langRedirect('/run/' . $id);
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
            $this->langRedirect('/run/' . $e->existingRunId . '?duplicate=1');
            return;
        }

        $this->langRedirect('/run/' . $newRunId . '?retrigger=1');
    }
}
