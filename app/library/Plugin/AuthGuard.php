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

    public function beforeDispatch(Event $event, Dispatcher $dispatcher): void
    {
        $route = $dispatcher->getControllerName() . ':' . $dispatcher->getActionName();

        if (in_array($route, self::PUBLIC_ROUTES, true)) {
            return;
        }

        if (!$this->session->has('userId')) {
            $dispatcher->forward(['controller' => 'auth', 'action' => 'login']);
        }
    }
}
