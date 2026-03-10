<?php

use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\View;

/** @var \Phalcon\Di\FactoryDefault $container */
$container->setShared('dispatcher', function (): Dispatcher {
    $dispatcher = new Dispatcher();
    $dispatcher->setDefaultNamespace('App\\controllers');

    return $dispatcher;
});

$container->setShared('view', function (): View {
    $view = new View();
    $view->setViewsDir(dirname(__DIR__) . '/views/');

    return $view;
});
