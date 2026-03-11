<?php

use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\View;
use Phalcon\Db\Adapter\Pdo\Mysql;   

/** @var \Phalcon\Di\FactoryDefault $container */
$container->setShared('dispatcher', function (): Dispatcher {
    $dispatcher = new Dispatcher();
    $dispatcher->setDefaultNamespace('app\\controllers');

    return $dispatcher;
});

$container->setShared('view', function (): View {
    $view = new View();
    $view->setViewsDir(dirname(__DIR__) . '/views/');

    return $view;
});

$container->setShared('db', function(): Mysql {
    $db = new Mysql([
        'host'     => APP_DATABASE_HOST,
        'username' => APP_DATABASE_USER,
        'password' => APP_DATABASE_PASS,
        'dbname'   => APP_DATABASE_NAME,
        'charset'  => APP_DATABASE_CHARSET
    ]);
    
    $db->getInternalHandler()->setAttribute(
          PDO::ATTR_DEFAULT_FETCH_MODE,
          PDO::FETCH_ASSOC
      );

    return $db;
});
