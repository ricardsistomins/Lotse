<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = new FactoryDefault();
$application = new Application($container);

require_once '/var/www/shared/config/config-lotse.php';
require dirname(__DIR__) . '/app/Bootstrap.php';

$bootstrap = new Bootstrap($container);
$bootstrap->bootstrap();

require dirname(__DIR__) . '/app/config/routes.php';

echo $application->handle($_SERVER['REQUEST_URI'] ?? '/')->getContent();

