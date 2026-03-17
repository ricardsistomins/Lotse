<?php

use Phalcon\Di\FactoryDefault\Cli as CliDi;
use Phalcon\Cli\Console;
use Phalcon\Cli\Dispatcher as CliDispatcher;

define('BASE_PATH', __DIR__);
require __DIR__ . '/vendor/autoload.php';

$container = new CliDi();
$console   = new Console($container);

require_once '/var/www/shared/config/config-lotse.php';
require __DIR__ . '/app/Bootstrap.php';

$bootstrap = new Bootstrap($container);
$bootstrap->bootstrap();

// Override web dispatcher with CLI dispatcher
$container->setShared('dispatcher', function () {
    $dispatcher = new CliDispatcher();
    $dispatcher->setDefaultNamespace('app\\tasks');
    
    return $dispatcher;
});

$arguments = [];

foreach ($argv as $k => $arg) {
    if ($k === 1) $arguments['task']   = $arg;
    if ($k === 2) $arguments['action'] = $arg;
    if ($k >= 3)  $arguments['params'][] = $arg;
}

$console->handle($arguments);
