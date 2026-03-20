<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;
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
        $this->view->setVar('runs', (new ResearchRunStorage())->getAll());
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
}
