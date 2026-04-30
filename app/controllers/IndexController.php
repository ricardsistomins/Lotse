<?php

namespace app\controllers;

class IndexController extends BaseController
{
    /**
     * Redirect to the default language dashboard.
     *
     * @return void
     */
    public function indexAction(): void
    {
        $this->langRedirect('/dashboard');

        return;
    }
}
