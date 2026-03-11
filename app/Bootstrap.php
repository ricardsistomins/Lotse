<?php

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Dispatcher;                                                              
use Phalcon\Mvc\View;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Session\Manager as SessionManager;
use Phalcon\Session\Adapter\Redis as SessionAdapterRedis;
use Phalcon\Storage\AdapterFactory;
use Phalcon\Storage\SerializerFactory;

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
     * @return mixed
     */
    public function bootstrap()
    {
        $this->initSession();
        $this->initDispatcher();
        $this->initView();
        $this->initDb();
  
        return $this->_di;
    }
    
    /**
     * Init dispatcher
     */
    protected function initDispatcher(): void
    {
        $this->_di->setShared('dispatcher', function (): Dispatcher {
            $dispatcher = new Dispatcher();
            $dispatcher->setDefaultNamespace('App\\controllers');
            
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
            $view->setViewsDir(dirname(__DIR__) . '/views/');
            
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
                'secure'   => true,
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
}