<?php

namespace app\controllers;

use app\Storage\UserStorage;
use app\Service\AuditService;
use app\Model\UserModel;

class UserController extends BaseController
{
    /**
     * Redirect non-admins away
     * 
     * @return bool
     */
    private function requireAdmin(): bool
    {
        if ($this->session->get('userRole') !== UserModel::ROLE_ADMIN) {
            $this->langRedirect('/dashboard');
            
            return false;
        }
        
        return true;
    }
    
    /**
     * List all users
     * 
     * @return void
     */
    public function indexAction(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        
        $session = $this->session;
        $flash = $session->get('_flash');
        $session->remove('_flash');
        
        $this->view->setVars([
            'users' => (new UserStorage())->getAll(),
            'flash' => $flash
        ]);
    }
    
    /**
     * Show user create form
     * 
     * @return void
     */
    public function createAction(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        
        $request = $this->request;
        
        if (!$request->isPost()) {
            return;
        }
        
        $name = trim($request->getPost('name', 'string'));
        $surname = trim($request->getPost('surname', 'string'));
        $email = trim($request->getPost('email', 'email'));
        $role = $request->getPost('role', 'string');
        $password = $request->getPost('password', 'string');
        $passwordConfirm = $request->getPost('password_confirm', 'string');
        
        $validRoles = [UserModel::ROLE_ADMIN, UserModel::ROLE_DEV, UserModel::ROLE_QA];
  
        if ($password !== $passwordConfirm) {
            $this->view->setVars([
                'error'   => 'Passwords do not match',
                'name'    => $name,
                'surname' => $surname,
                'email'   => $email,
                'role'    => $role
            ]);
            
            return;
        }
        
        if (!$name || !$surname || !$email || !in_array($role, $validRoles) || strlen($password) < 8) {
            $this->view->setVars([
                'error'   => 'All fields are required. Password must be at least 8 characters.', 
                'name'    => $name,
                'surname' => $surname,
                'email'   => $email,
                'role'    => $role
            ]);
            
            return;
        }
        
        $userStorage = new UserStorage();
        $userId = $userStorage->create($name, $surname, $email, $role, password_hash($password, PASSWORD_DEFAULT));
        
        (new AuditService($this->db))->log(
            actorType: 'user',
            actorUserId: (int)$this->session->get('userId'),
            action:      'user.created',
            entityType:  'user',
            entityId:    $userId,
            metadata:    [
                'email' => $email, 
                'role' => $role
            ]
        );
        
        $this->setFlash('success', $this->translate('User created successfully.'));
        $this->langRedirect('/users');
    }
    
    /**
     * Edit user info
     * 
     * @return void
     */
    public function editAction(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        
        $id = $this->dispatcher->getParam('id');
        $user = (new UserStorage())->getById($id);
        
        if (!$user) {
            $this->langRedirect('/users');
            return;
        }
        
        $this->view->setVar('user', $user);
    }
    
    /**
     * Save user data
     * 
     * @return void
     */
    public function saveAction(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        
        $id = $this->dispatcher->getParam('id');
        $userStorage = new UserStorage();
        $user = $userStorage->getById($id);
        
        if (!$user) {
            $this->langRedirect('/users');
            return;
        }
        
        $request = $this->request;
        
        $name = trim($request->getPost('name', 'string'));
        $surname = trim($request->getPost('surname', 'string'));
        $email = trim($request->getPost('email', 'email'));
        $role = $request->getPost('role', 'string');
        $isActive = (bool)$request->getPost('is_active', 'int');
        $password = $request->getPost('password', 'string');
        
        $validRoles = [UserModel::ROLE_ADMIN, UserModel::ROLE_DEV, UserModel::ROLE_QA];
        
        if (!$name || !$surname || !$email || !in_array($role, $validRoles)) {
            $this->view->setVars([
                'error' => 'All fields are required.',
                'user'  => $user
            ]);
            
            $this->view->pick('user/edit');
            return;
        }
        
        if ($password && strlen($password) < 8) {
            $this->view->setVars([
                'error' => 'Password must be at least 8 characters',
                'user'  => $user
            ]);
            
            $this->view->pick('user/edit');
            return;
        }
        
        $wasActive = (bool)$user->isActive;
        $passwordHash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
        
        $userStorage->update($id, $name, $surname, $email, $role, $isActive, $passwordHash);
        $action = ($wasActive && !$isActive) ? 'user.deactivated' : 'user.updated';
        
        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: (int)$this->session->get('userId'),
            action:      $action,
            entityType:  'user',
            entityId:    $id,
            metadata:    [
                'role' => $role, 
                'is_active' => $isActive
            ]
        );

        $this->setFlash('success', $this->translate('User updated successfully.'));
        $this->langRedirect('/users');
    }
}
