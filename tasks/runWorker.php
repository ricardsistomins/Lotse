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
use app\Service\{
    ResearchRunOrchestrator, 
    DuplicateRunException
};
use app\Storage\{
    SystemSettingsStorage,
    ResearchRunStorage
};    
use app\Model\ResearchRunModel;

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
 * iterates over the queries and calls ResearchRunOrchestrator->run() for each one.                                        
 * On success: logs the completed run ID and moves to the next query.                                                      
 * On DuplicateRunException: skips silently — a run for this query is already in progress.                                 
 * On transient error: retries up to 3 times with backoff (30s, 120s, 600s).                                               
 *     Each retry increments research_runs.retry_count on the existing run row.                                            
 * After all attempts exhausted: logs the final error and moves to the next query.                                         
 */   
$db  = $container->get('db');
$orchestrator = new ResearchRunOrchestrator();

$runStorage = new ResearchRunStorage();              
$backoff = [30, 120, 600];
$maxAttempts = 4;

foreach ($queries as $query) {
    echo timestamp() . ' Starting: ' . $query . PHP_EOL;

    $existingRunId = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $runId = $orchestrator->run(
                triggerSource: ResearchRunModel::TRIGGER_CRON,
                query:         $query,
                userId:        null,
                db:            $db,
                existingRunId: $existingRunId
            );

            echo timestamp() . ' Completed run #' . $runId . PHP_EOL;
            break;

        } catch (DuplicateRunException $e) {
            echo timestamp() . ' Skipped (duplicate of run #' . $e->existingRunId . ')' . PHP_EOL;
            break;

        } catch (\Throwable $e) {
            $existingRunId = $orchestrator->lastRunId ?: null;

            if ($attempt < $maxAttempts) {
                $wait = $backoff[$attempt - 1];

                if ($existingRunId) {
                    $runStorage->incrementRetryCount($existingRunId);
                }

                echo timestamp() . ' Attempt ' . $attempt . '/' . $maxAttempts . ' after transient error: ' . $e->getMessage() . PHP_EOL;
                echo timestamp() . ' Retrying in ' . $wait . 's...' . PHP_EOL;
                sleep($wait);
            } else {
                echo timestamp() . ' All ' . $maxAttempts . ' attempts exhausted for: ' . $query . PHP_EOL;
                echo timestamp() . ' Final error: ' . $e->getMessage() . PHP_EOL;
            }
        }
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
