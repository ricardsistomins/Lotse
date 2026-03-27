<?php

namespace app\controllers;                                                    
                  
use Phalcon\Mvc\Controller;                                                   

use app\Storage\SystemSettingsStorage;                                        
use app\Service\AuditService;
use app\Model\UserModel;

class SettingsController extends Controller                                   
{
    /**                                                                       
     * List all setting keys.
     *
     * @return void
     */
    public function indexAction(): void
    {
        $this->view->setVar('settings', (new SystemSettingsStorage())->getAll());                                          
    }

    /**         
     * Show and edit a single setting by key.
     *                                                                        
     * @param string $key
     * @return void                                                           
     */         
    public function viewAction(string $key): void
    {
        $value = (new SystemSettingsStorage())->get($key);

        if ($value === null) {
            $this->response->redirect('/settings');                           
            $this->response->send();

            return;
        }     
        
        if ($key === 'provider_profiles') {
            $value = $this->maskApiKeys($value);                                      
        }                          

        $this->view->setVars([
            'key'   => $key,
            'value' => json_encode($value, JSON_PRETTY_PRINT),
        ]);                                                                   
    }

    /**         
     * Save an updated setting value.
     *
     * @param string $key
     * @return void
     */
    public function saveAction(string $key): void
    {                                                                         
        $response = $this->response;
        $userRole = $this->session->get('userRole');                          

        if (!in_array($userRole, [UserModel::ROLE_ADMIN, UserModel::ROLE_DEV])) {                             
            $response->redirect('/settings');
            $response->send();                                                

            return;
        }

        $raw = trim($this->request->getPost('value'));                    
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->view->setVars([
                'key'   => $key,
                'value' => $raw,
                'error' => 'Invalid JSON: ' . json_last_error_msg(),
            ]);

            $this->view->pick('settings/view');

            return;
        }

        $userId = (int)$this->session->get('userId');

        if ($key === 'provider_profiles') {
            $current = (new SystemSettingsStorage())->get($key);                      
            $decoded = $this->restoreApiKeys($decoded, $current);                     
        }

        (new SystemSettingsStorage())->set($key, $decoded, $userId);

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,                                             
            action:      'settings.updated',
            entityType:  'system_settings',                                   
            entityId:    0,                                                   
            metadata:    ['key' => $key]
        );                                                                    

        $value = (new SystemSettingsStorage())->get($key);

        if ($key === 'provider_profiles') {
            $value = $this->maskApiKeys($value);
        }

        $this->view->setVars([
            'key'     => $key,
            'value'   => json_encode($value, JSON_PRETTY_PRINT),
            'success' => 'Settings saved successfully.',
        ]);
        
        $this->view->pick('settings/view');
    }              
    
    /**                                                                           
     * Recursively replace api_key values with *** for display.
     *
     * @param mixed $data
     * @return mixed                                                              
     */
    private function maskApiKeys(mixed $data): mixed                              
    {               
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => &$value) {                                            
            if ($key === 'api_key') {
                $value = '***';                                                       
            } else {
                $value = $this->maskApiKeys($value);
            }
        }

        return $data;                                                             
    }

    /**             
     * Restore original api_key values where the submitted value is ***.
     *
     * @param mixed $submitted
     * @param mixed $current
     * @return mixed                                                              
     */
    private function restoreApiKeys(mixed $submitted, mixed $current): mixed      
    {               
        if (!is_array($submitted) || !is_array($current)) {
            return $submitted;
        }

        foreach ($submitted as $key => &$value) {                                       
            if ($key === 'api_key' && $value === '***') {
                $value = $current[$key] ?? '';                                          
            } else {
                $value = $this->restoreApiKeys($value, $current[$key] ?? []);
            }                                                                     
        }

        return $submitted;
    }
}               
