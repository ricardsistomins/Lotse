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

$container->setShared('router', $router);
