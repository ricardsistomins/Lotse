<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;

class IndexController extends Controller
{
    public function indexAction(): void
    {
        $this->response->redirect('/dashboard');
        $this->response->send();   

        return;  
    }
}
