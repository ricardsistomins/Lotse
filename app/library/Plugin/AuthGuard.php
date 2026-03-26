<?php

namespace app\Plugin;

use Phalcon\Di\Injectable;
use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;

class AuthGuard extends Injectable
{
    private const PUBLIC_ROUTES = [
        'auth:login',
        'auth:logout',
        'error:notFound',
    ];

    // 30 min
    private const SESSION_TIMEOUT = 1800;
    
    public function beforeDispatch(Event $event, Dispatcher $dispatcher): void
    {
        $session = $this->session;
        $route = $dispatcher->getControllerName() . ':' . $dispatcher->getActionName();

        if (in_array($route, self::PUBLIC_ROUTES, true)) {
            return;
        }

        if (!$session->has('userId')) {
            $dispatcher->forward([
                'controller' => 'auth', 
                'action' => 'login'
            ]);
            
            return;
        }
        
        $lastActivity = $session->get('lastActivity');
        
        if ($lastActivity !== null && (time() - $lastActivity) > self::SESSION_TIMEOUT) {
            $session->destroy();
            $session->start();
            
            $session->set('sessionExpired', true);
            $dispatcher->forward([
                'controller' => 'auth', 
                'action' => 'login'
            ]);
            
            return;
        }
        
        $session->set('lastActivity', time());
    }
}
