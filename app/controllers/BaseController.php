<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;

class BaseController extends Controller
{
    /**
     * Get the current language from route param or session
     * 
     * @return string
     */
    protected function getLang(): string
    {
        return $this->dispatcher->getParam('lang', 'string') ?: $this->session->get('language', 'de');
    }
    
    /**
     * Redirect to a path prefixed with the current language
     * 
     * @param string $path
     * @return void
     */
    protected function langRedirect(string $path): void 
    {
        $response = $this->response;
        
        $response->redirect('/' . $this->getLang() . $path);
        $response->send();
    }
    
    /**             
     * Store a one-time flash message in the session.                                                             
     *              
     * @param string $type     Bootstrap alert type: success, warning, danger, info
     * @param string $message                                                                                     
     * @return void
     */                                                                                                           
    protected function setFlash(string $type, string $message): void
    {                                                                                                             
        $this->session->set('_flash', ['type' => $type, 'message' => $message]);
    }
}