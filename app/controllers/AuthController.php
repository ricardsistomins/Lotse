<?php

namespace app\controllers;

use app\Storage\UserStorage;
use app\Validator\LoginValidator;
use app\Service\AuditService;

class AuthController extends BaseController
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
        
        $activeLanguage = $this->session->get('language') ?? 'de';
        
        $sessionExpired = $session->get('sessionExpired', false);
        
        if ($sessionExpired) {
            $session->remove('sessionExpired');
            $view->setVar('sessionExpired', true);
        }
       
        if ($session->has('userId')) {
            $response->redirect('/' . $activeLanguage . '/dashboard');
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
        
        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $user->userId,
            action:      'auth.login',
            entityType:  'user',
            entityId:    $user->userId,
            metadata:    []
        );

        $response->redirect('/' . $activeLanguage . '/dashboard');
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
        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: (int)$this->session->get('userId'),
            action:      'auth.logout',
            entityType:  'user',
            entityId:    (int)$this->session->get('userId'),
            metadata:    []
        );
    
        $this->session->destroy();
        $this->response->redirect('/auth/login');
        $this->response->send();
        
        return;
    }
}
