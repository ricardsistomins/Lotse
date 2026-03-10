<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = new FactoryDefault();

$application = new Application($container);

require dirname(__DIR__) . '/app/config/services.php';
require dirname(__DIR__) . '/app/config/routes.php';

echo $application->handle($_SERVER['REQUEST_URI'] ?? '/')->getContent();

