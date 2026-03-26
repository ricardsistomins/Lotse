<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;
use app\Storage\UserStorage;
use app\Validator\LoginValidator;

class AuthController extends Controller
{
    /*
     * User login action
     */
    public function loginAction(): void
    {
        // This disables the layout for login page only. All other pages will use dashboard.phtml automatically.
        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        $session = $this->session;
        $response = $this->response;
        $request = $this->request;
        $view = $this->view;
        
        $sessionExpired = $session->get('sessionExpired', false);
        
        if ($sessionExpired) {
            $session->remove('sessionExpired');
            $view->setVar('sessionExpired', true);
        }
       
        if ($session->has('userId')) {
            $response->redirect('/dashboard');
            $response->send();

            return;
        }
        
        if (!$request->isPost()) {
            return;
        }
        
        $email = $request->getPost('email', 'email');
        $password = $request->getPost('password', 'string');

        $error = (new LoginValidator())->validate($email, $password);
        
        if ($error) {
            $view->setVar('error', $error);
            $view->setVar('email', $email);
            
            return;
        }
        
        $userStorage = new UserStorage();
        $user = $userStorage->getUserByEmail($email);
        
        if (!$user || !$user->isActive || !password_verify($password, $user->passwordHash)) {
            $view->setVar('error', 'Invalid email or password');
            $view->setVar('email', $email);
            
            return;
        }
        
        $userStorage->updateLastLoginField($user->userId);
        
        $session->set('userId', $user->userId);
        $session->set('userRole', $user->role);
        $session->set('userName', $user->name);
        
        $response->redirect('/dashboard');
        $response->send();
        
        return;
    }
    
    /**
     * User logout action
     * 
     * @return void
     */
    public function logoutAction(): void
    {
        $this->session->destroy();
        $this->response->redirect('/auth/login');
        $this->response->send();
        
        return;
    }
}
