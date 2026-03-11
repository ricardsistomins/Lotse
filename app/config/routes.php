<?php

use Phalcon\Mvc\Router;

/** @var \Phalcon\Di\FactoryDefault $container */
$router = new Router();

$router->add(
    '/',
    [
        'controller' => 'index',
        'action'     => 'index',
    ]
);

$router->add('/', array(
    'controller'  => 'index',
    'action'      => 'index'
));

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

$container->setShared('router', $router);
