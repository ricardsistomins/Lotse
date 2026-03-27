<?php 

declare(strict_types=1);

/**                                                                                        
 * CLI Research Run Worker
 *
 * Usage:
 * php app/tasks/run_worker.php 
 *     # runs all queries from system_settings `cron_queries`                                                             
 *  
 * php app/tasks/run_worker.php --query "AI funding"  
 *     # runs a single query
 *                                                                                         
 * Cron example (every hour):
 *     0 * * * * /usr/bin/php /var/www/lotse/app/tasks/tasks/runWorker.php >> /var/log/lotse/cron_worker.log 2>&1     
 * 
 * For manually run cron:                                                   
 *     1. cd /var/www/lotse
 *     2. php tasks/runWorker.php --query "..."
 */

// Guard - exits immediately if not called from CLI
if (php_sapi_name() !== 'cli') {
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));                                                   
require BASE_PATH . '/vendor/autoload.php';
require_once '/var/www/shared/config/config-lotse.php';                                    

use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Autoload\Loader;                                                               
use app\Service\{ResearchRunOrchestrator, DuplicateRunException};
use app\Storage\SystemSettingsStorage;                   

/**
 * Bootstrap:
 *     # loads autoloader and config, 
 *     # then sets up a minimal DI container with only two services: 
 *         - the class loader (so Phalcon can find all the Storage/Model/Service classes) 
 *         - and the DB connection
 *
 */
$container = new FactoryDefault();                                                         

$loader = new Loader();
$loader->setDirectories([
    BASE_PATH . '/app/library/Storage/',
    BASE_PATH . '/app/library/Model/',
    BASE_PATH . '/app/library/Validator/',                                                 
    BASE_PATH . '/app/library/Plugin/',
    BASE_PATH . '/app/library/Service/',                                                   
    BASE_PATH . '/app/library/Provider/',
    BASE_PATH . '/app/library/Provider/Response/',
    BASE_PATH . '/app/library/Provider/LLM/',                                              
    BASE_PATH . '/app/library/Provider/Search/',
    BASE_PATH . '/app/tasks/',                                                             
])->register(); 

$container->setShared('loader', $loader);                                                  

$container->setShared('db', function (): Mysql {                                           
    $db = new Mysql([
        'host'     => APP_DATABASE_HOST,
        'username' => APP_DATABASE_USER,
        'password' => APP_DATABASE_PASS,
        'dbname'   => APP_DATABASE_NAME,                                                   
        'charset'  => APP_DATABASE_CHARSET,
    ]);                                                                                    

    $db->getInternalHandler()->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $db; 
});

/**
 * Resolve queries
 * checks if query "..." was passed as a CLI argument; 
 * if yes, uses that single query; 
 * if no, reads the cron_queries array from system_settings in the database
 */ 
$opts = getopt('', ['query:']);
$queries = [];

if (isset($opts['query']) && trim((string)$opts['query']) !== '') {                        
    $queries = [trim((string)$opts['query'])];
} else {                                                                                   
    $cronQueries = (new SystemSettingsStorage())->get('cron_queries');
    $queries     = is_array($cronQueries) ? array_filter(array_map('trim', $cronQueries)) : [];                                                                                       
}

if (empty($queries)) {
    echo timestamp() . ' No queries to run. Add cron_queries to system_settings or pass --query.'
. PHP_EOL;                                                                                 
    exit(0);
}                                                                                          
                  
/**
 * Run loop
 * iterates over the queries and calls ResearchRunOrchestrator->run() 
 * for each one with triggerSource: 'cron'; 
 * catches DuplicateRunException to skip without error, 
 * catches any other exception to log and continue to the next query
 */
$db  = $container->get('db');
$orchestrator = new ResearchRunOrchestrator();

foreach ($queries as $query) {                                                             
    echo timestamp() . ' Starting: ' . $query . PHP_EOL;

    try {       
        $runId = $orchestrator->run(
            triggerSource: 'cron',
            query:         $query,
            userId:        null,
            db:            $db                                                             
        );
        
        echo timestamp() . ' Completed run #' . $runId . PHP_EOL;                                 
    } catch (DuplicateRunException $e) {
        echo timestamp() . ' Skipped (duplicate of run #' . $e->existingRunId . ')' . PHP_EOL;
    } catch (\Throwable $e) {                                                              
        echo timestamp() . ' ERROR: ' . $e->getMessage() . PHP_EOL;
    }                                                                                      
}               

echo timestamp() . ' Worker finished.' . PHP_EOL;                                                 
   
/**
 * Helper
 * 
 * @return string
 */           
function timestamp(): string
{
    return '[' . date('Y-m-d H:i:s') . ']';
}
