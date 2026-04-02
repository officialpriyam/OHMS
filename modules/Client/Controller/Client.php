<?php
/**
 * OHMS.
 *
 * @copyright OHMS, Inc (https://www.OHMS.org)
 * @license   Apache-2.0
 *
 * Copyright OHMS, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * ---
 *
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */



namespace Priyx\Mod\Client\Controller;

class Client implements \Priyx\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function register(\Priyx_App &$app)
    {
        // @deprecated
        $app->get('/client/me', 'get_profile', [], get_class($this));

        $app->get('/login', 'get_login_page', [], get_class($this));
        $app->get('/register', 'get_register_page', [], get_class($this));
        $app->get('/signup', 'get_signup_redirect', [], get_class($this));
        $app->get('/client/reset-password-confirm/:hash', 'get_reset_password_confirm', ['hash' => '[a-z0-9]+'], get_class($this));
        $app->get('/client', 'get_client_index', [], get_class($this));
        $app->get('/client/logout', 'get_client_logout', [], get_class($this));
        $app->get('/client/:page', 'get_client_page', ['page' => '[a-z0-9-]+'], get_class($this));
        $app->get('/client/confirm-email/:hash', 'get_client_confirmation', ['page' => '[a-z0-9-]+'], get_class($this));
    }

    /**
     * @param Priyx_App $app
     *
     * @deprecated
     */
    public function get_profile(\Priyx_App $app)
    {
        return $app->redirect('/client/profile');
    }

    /**
     * @param Priyx_App $app
     *
     * @deprecated
     */
    public function get_balance(\Priyx_App $app)
    {
        return $app->redirect('/client/balance');
    }

    public function get_client_index(\Priyx_App $app)
    {
        $this->di['is_client_logged'];

        return $app->render('mod_dashboard_index');
    }

    public function get_login_page(\Priyx_App $app)
    {
        if ($this->di['auth']->isClientLoggedIn()) {
            return $app->redirect('/client');
        }

        return $app->render('mod_page_login');
    }

    public function get_register_page(\Priyx_App $app)
    {
        if ($this->di['auth']->isClientLoggedIn()) {
            return $app->redirect('/client');
        }

        return $app->render('mod_page_register');
    }

    public function get_signup_redirect(\Priyx_App $app)
    {
        return $app->redirect('/register');
    }

    public function get_client_confirmation(\Priyx_App $app, $hash)
    {
        $service = $this->di['mod_service']('client');
        $service->approveClientEmailByHash($hash);
        $systemService = $this->di['mod_service']('System');
        $systemService->setPendingMessage(__('Email address was confirmed'));
        $app->redirect('/client');
    }

    public function get_client_logout(\Priyx_App $app)
    {
        $api = $this->di['api_client'];
        $api->profile_logout();
        $app->redirect('/');
    }

    public function get_client_page(\Priyx_App $app, $page)
    {
        $this->di['is_client_logged'];
        $template = 'mod_client_'.$page;

        return $app->render($template);
    }

    public function get_reset_password_confirm(\Priyx_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];
        $api->client_confirm_reset($data);
        $app->redirect('/login');
    }
}
