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

$container->setShared('router', $router);
