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

$container->setShared('router', $router);
