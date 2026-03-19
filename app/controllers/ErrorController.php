<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;

class ErrorController extends Controller
{
    public function notFoundAction(): void
    {
        $response = $this->response;
        
        $this->view->disable();
        $response->setStatusCode(404, 'Not Found');
        $response->setContent(file_get_contents(__DIR__ . '/../views/error/notfound.phtml'));
        $response->send();
    }
}
