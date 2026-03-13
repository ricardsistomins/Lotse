<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;

class ErrorController extends Controller
{
    public function notFoundAction(): void
    {
        $this->view->disable();
        $this->response->setStatusCode(404, 'Not Found');
        $this->response->setContent(file_get_contents(__DIR__ . '/../views/error/notfound.phtml'));
        $this->response->send();
    }
}
