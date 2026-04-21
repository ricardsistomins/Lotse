<?php

use Phalcon\Mvc\Router;

/** @var \Phalcon\Di\FactoryDefault $container */
$router = new Router();

$router->add('/', array(
    'controller'  => 'index',
    'action'      => 'index'
));


/******************************
 * 
 * Error page
 * 
 *****************************/
$router->notFound([
    'controller' => 'error',
    'action'     => 'notFound',
]);


/******************************
 * 
 * Authentification
 *
 *****************************/
$router->add('/auth/login', array(
    'controller' => 'auth',
    'action'     => 'login'
));

$router->add('/auth/logout', array(
    'controller' => 'auth',
    'action'     => 'logout'
));


/******************************
 * 
 * Dashboard
 * 
 *****************************/
$router->add('/dashboard', array(
    'controller' => 'dashboard',
    'action'     => 'index'
));

$router->add('/dashboard/trigger', array(
    'controller' => 'dashboard',
    'action'     => 'trigger',
));


/******************************
 * 
 * Report
 * 
 *****************************/
$router->add('/reports', array(
    'controller' => 'report', 
    'action'     => 'index'
));  

$router->add('/report/{id:[0-9]+}', array(
    'controller' => 'report', 
    'action'     =>'view'
));

$router->add('/report/{id:[0-9]+}/save', array(
    'controller' => 'report', 
    'action'     => 'save'
));

$router->add('/report/{id:[0-9]+}/status', array(
    'controller' => 'report', 
    'action'     => 'updateStatus'
));   

$router->add('/report/{id:[0-9]+}/customer', array(
    'controller' => 'report', 
    'action'     => 'saveCustomer'
));   

$router->add('/report/{id:[0-9]+}/preview', [                                 
    'controller' => 'report',                                                 
    'action'     => 'preview'           
]); 
  
$router->add('/finding/{id:[0-9]+}/edit', array(
    'controller' => 'report', 
    'action'     => 'editFinding'
)); 

/******************************
*
* Runs
*
*****************************/
$router->add('/runs', array(
    'controller' => 'run',
    'action'     => 'index'
));

$router->add('/run/{id:[0-9]+}', array(
    'controller' => 'run',
    'action'     => 'view'
));

$router->add('/run/{id:[0-9]+}/retrigger', array(
    'controller' => 'run',
    'action'     => 'retrigger'
));


/******************************                                               
*                                                                             
* QA
*                                                                             
******************************/                           
$router->add('/qa', [
    'controller' => 'qa',                                                     
    'action'     => 'index'
]);                                                                           

$router->add('/qa/{revisionId:[0-9]+}/approve', [                             
    'controller' => 'qa',
    'action'     => 'approve'                                                 
]);                                                                           

$router->add('/qa/{revisionId:[0-9]+}/reject', [                              
    'controller' => 'qa',                                 
    'action'     => 'reject'                                                  
]);  


/******************************
*                                                                             
* Settings
*                                                                             
******************************/
$router->add('/settings', [
    'controller' => 'settings',
    'action'     => 'index'
]);                                                                           

$router->add('/settings/{key:[a-z_]+}', [                                     
    'controller' => 'settings',
    'action'     => 'view'
]);                                                                           

$router->add('/settings/{key:[a-z_]+}/save', [                                
    'controller' => 'settings',
    'action'     => 'save'
]);


/******************************
*
* Customers
*
******************************/
$router->add('/customers', [
    'controller' => 'customer',
    'action'     => 'index'
]);

$router->add('/customer/{id:[0-9]+}', [
    'controller' => 'customer',
    'action'     => 'view'
]);

$router->add('/customer/{id:[0-9]+}/save', [
    'controller' => 'customer',
    'action'     => 'save'
]);

  
$container->setShared('router', $router);
