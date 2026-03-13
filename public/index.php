<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Application;

define('BASE_PATH', dirname(__DIR__));
require dirname(__DIR__) . '/vendor/autoload.php';

$container = new FactoryDefault();
$application = new Application($container);

require_once '/var/www/shared/config/config-lotse.php';
require dirname(__DIR__) . '/app/Bootstrap.php';

$bootstrap = new Bootstrap($container);
$bootstrap->bootstrap();

require dirname(__DIR__) . '/app/config/routes.php';

try {
    echo $application->handle($_SERVER['REQUEST_URI'] ?? '/')->getContent();
} catch (\Phalcon\Mvc\Dispatcher\Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(404);
    require dirname(__DIR__) . '/app/views/error/notfound.phtml';
}
