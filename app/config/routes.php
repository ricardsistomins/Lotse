 <?php                                                                         
                  
use Phalcon\Mvc\Router;                                                       

/** @var \Phalcon\Di\FactoryDefault $container */                             
$router = new Router();

$router->add('/', [
    'controller' => 'index',                                                  
    'action'     => 'index'
]);

$router->notFound([                                                           
    'controller' => 'error',
    'action'     => 'notFound',                                               
]);             


/******************************
 * Authentification                                                           
 *****************************/
$router->add('/auth/login', [
    'controller' => 'auth',
    'action'     => 'login'                                                   
]);

$router->add('/auth/logout', [
    'controller' => 'auth',
    'action'     => 'logout'
]);

$router->add('/auth/theme/toggle', [
    'controller' => 'auth',
    'action'     => 'toggleTheme',                                                                                       
]);

/******************************
 * Dashboard
 *****************************/                                               
$router->add('/{lang:[a-z]{2}}/dashboard', [
    'controller' => 'dashboard',                                              
    'action'     => 'index'
]);

$router->add('/{lang:[a-z]{2}}/dashboard/trigger', [                          
    'controller' => 'dashboard',
    'action'     => 'trigger',                                                
]);             


/******************************
 * Report
 *****************************/
$router->add('/{lang:[a-z]{2}}/reports', [
    'controller' => 'report',                                                 
    'action'     => 'index'
]);                                                                           

$router->add('/{lang:[a-z]{2}}/report/{id:[0-9]+}', [                         
    'controller' => 'report',
    'action'     => 'view'                                                    
]);             

$router->add('/{lang:[a-z]{2}}/report/{id:[0-9]+}/save', [
    'controller' => 'report',                                                 
    'action'     => 'save'
]);

$router->add('/{lang:[a-z]{2}}/report/{id:[0-9]+}/status', [                  
    'controller' => 'report',
    'action'     => 'updateStatus'                                            
]);             

$router->add('/{lang:[a-z]{2}}/report/{id:[0-9]+}/customer', [                
    'controller' => 'report',
    'action'     => 'saveCustomer'                                            
]);             

$router->add('/{lang:[a-z]{2}}/report/{id:[0-9]+}/preview', [                 
    'controller' => 'report',
    'action'     => 'preview'                                                 
]);             

$router->add('/{lang:[a-z]{2}}/report/{id:[0-9]+}/retrigger', [                                              
    'controller' => 'report',
    'action'     => 'retrigger'
]);    

$router->add('/{lang:[a-z]{2}}/report/{id:[0-9]+}/export', [
    'controller' => 'report',
    'action'     => 'export',
]);

$router->add('/{lang:[a-z]{2}}/report/{id:[0-9]+}/export-preview', [
    'controller' => 'report',
    'action'     => 'exportPreview',
 ]);


$router->add('/{lang:[a-z]{2}}/finding/{id:[0-9]+}/edit', [                   
    'controller' => 'report',
    'action'     => 'editFinding'                                             
]);             


/******************************
 * Runs
 *****************************/
$router->add('/{lang:[a-z]{2}}/runs', [
    'controller' => 'run',                                                    
    'action'     => 'index'
]);                                                                           

$router->add('/{lang:[a-z]{2}}/run/{id:[0-9]+}', [                            
    'controller' => 'run',
    'action'     => 'view'                                                    
]);             

$router->add('/{lang:[a-z]{2}}/run/{id:[0-9]+}/retrigger', [                  
    'controller' => 'run',
    'action'     => 'retrigger'                                               
]);             


/******************************
 * QA
 *****************************/
$router->add('/{lang:[a-z]{2}}/qa', [
    'controller' => 'qa',                                                     
    'action'     => 'index'
]);                                                                           

$router->add('/{lang:[a-z]{2}}/qa/{revisionId:[0-9]+}/approve', [             
    'controller' => 'qa',
    'action'     => 'approve'                                                 
]);             

$router->add('/{lang:[a-z]{2}}/qa/{revisionId:[0-9]+}/reject', [              
    'controller' => 'qa',
    'action'     => 'reject'                                                  
]);             

$router->add('/{lang:[a-z]{2}}/qa/{id:[0-9]+}/retrigger', [                              
    'controller' => 'qa',                                                                  
    'action'     => 'retrigger'                                                      
]); 

/******************************
 * Settings
 *****************************/
$router->add('/{lang:[a-z]{2}}/settings', [
    'controller' => 'settings',
    'action'     => 'index'                                                   
]);

$router->add('/{lang:[a-z]{2}}/settings/audit-log', [                
    'controller' => 'settings',
    'action'     => 'auditLog'
]);

$router->add('/{lang:[a-z]{2}}/settings/{key:[a-z_]+}', [
    'controller' => 'settings',
    'action'     => 'view'
]);                                                                           

$router->add('/{lang:[a-z]{2}}/settings/{key:[a-z_]+}/save', [                
    'controller' => 'settings',
    'action'     => 'save'
]);                                                                                                                                                     

$router->add('/{lang:[a-z]{2}}/settings/trusted-sources', [
    'controller' => 'settings',
    'action'     => 'trustedSources'
]);

$router->add('/{lang:[a-z]{2}}/settings/trusted-sources/add', [
    'controller' => 'settings',
    'action'     => 'trustedSourcesAdd'
]);

$router->add('/{lang:[a-z]{2}}/settings/trusted-sources/remove', [
    'controller' => 'settings',
    'action'     => 'trustedSourcesRemove'
]);

/******************************
 * Users
 *****************************/
$router->add('/{lang:[a-z]{2}}/users', [
   'controller' => 'user',
   'action'     => 'index',
]);

$router->add('/{lang:[a-z]{2}}/user/create', [
   'controller' => 'user',
   'action'     => 'create',
]);

$router->add('/{lang:[a-z]{2}}/user/{id:[0-9]+}/edit', [
   'controller' => 'user',
   'action'     => 'edit',
]);

$router->add('/{lang:[a-z]{2}}/user/{id:[0-9]+}/save', [
   'controller' => 'user',
   'action'     => 'save',
]);


/******************************
 * Customers
 *****************************/
$router->add('/{lang:[a-z]{2}}/customers', [
    'controller' => 'customer',
    'action'     => 'index'
]);

$router->add('/{lang:[a-z]{2}}/customer/{id:[0-9]+}', [
    'controller' => 'customer',                                               
    'action'     => 'view'
]);

$router->add('/{lang:[a-z]{2}}/customer/{id:[0-9]+}/save', [                  
    'controller' => 'customer',
    'action'     => 'save'                                                    
]);             


$container->setShared('router', $router);
