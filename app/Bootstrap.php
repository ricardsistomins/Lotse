<?php

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Events\Manager as EventsManager;                                                              
use Phalcon\Mvc\View;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Session\Manager as SessionManager;
use Phalcon\Session\Adapter\Redis as SessionAdapterRedis;
use Phalcon\Storage\AdapterFactory;
use Phalcon\Storage\SerializerFactory;
use Phalcon\Autoload\Loader; 
use app\Plugin\AuthGuard;

/**
 * Class Bootstrap
 */
class Bootstrap
{
    private DiInterface $_di;
    
    /**
     * Constructor
     * 
     * @param DiInterface $di
     */
    public function __construct(DiInterface $di)
    {
        $this->_di = $di;
    }
 
    /**
     * Runs the application performing all initializations
     *
     * @return DiInterface
     */
    public function bootstrap(): DiInterface
    {
        $this->initLoader();
        $this->initSession();
        $this->initDispatcher();
        $this->initView();
        $this->initDb();
        $this->initAuditService();
  
        return $this->_di;
    }
    
    /**
     * Init dispatcher
     */
    protected function initDispatcher(): void
    {
        $this->_di->setShared('dispatcher', function (): Dispatcher {
            $eventsManager = new EventsManager();

            $eventsManager->attach('dispatch', new AuthGuard());
            
            $eventsManager->attach('dispatch:beforeException', function ($event, $dispatcher, $exception) {
                $dispatcher->forward(['controller' => 'error', 'action' => 'notFound']);
                return false;
            });

            $dispatcher = new Dispatcher();
            $dispatcher->setDefaultNamespace('app\\controllers');
            $dispatcher->setEventsManager($eventsManager);

            return $dispatcher;
        });
    }

    /**
     * Init view
     */
    protected function initView(): void
    {
        $this->_di->setShared('view', function (): View {
            $view = new View();
            $view->setViewsDir(__DIR__ . '/views/');
            
            // Base layout by default - dashboard.phtml
            $view->setLayout('dashboard');
            
            return $view;
        });
    }

    /**
     * Initializes database connection
     */
    protected function initDb(): void
    {
        $this->_di->setShared('db', function (): Mysql {
            $db = new Mysql([
                'host'     => APP_DATABASE_HOST,
                'username' => APP_DATABASE_USER,
                'password' => APP_DATABASE_PASS,
                'dbname'   => APP_DATABASE_NAME,
                'charset'  => APP_DATABASE_CHARSET,
            ]);

            $db->getInternalHandler()->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            return $db;
        });
    }

    /**
     * Init session
     */
    protected function initSession(): void
    {
        $this->_di->setShared('session', function() {
            session_set_cookie_params([
                'secure'   => false,//true
                'samesite' => 'Lax'
            ]);
            
            $session = new SessionManager();
            $adapterFactory = new AdapterFactory(new SerializerFactory());
            
            $adapter = new SessionAdapterRedis($adapterFactory, [
                'host'       => '/var/run/redis/redis.sock',
                'port'       => 0,
                'prefix'     => 'session:',
                'lifetime'   => 43200,
                'persistent' => false,
                'index'      => 0
            ]);

            $session
                ->setAdapter($adapter)
                ->setName('lotsesid')
                ->start();

            return $session;
        });
    }
    
    /**
     * Init loader
     */
    protected function initLoader(): void
    {
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
            BASE_PATH . '/app/library/Provider/Search/'
        ])->register();

        $this->_di->setShared('loader', $loader);
    }
    
    /**
     * Init audit service
     */
    protected function initAuditService(): void
    {
        $di = $this->_di;
        
        $this->_di->setShared('auditService', function () use ($di) {
            return new \app\Service\AuditService($di->get('db'));
        });
    }
}