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



/**
 * This file connects OHMS client area interface and API
 * Class does not extend any other class.
 */

namespace Priyx\Mod\Example\Controller;

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

    /**
     * Methods maps client areas urls to corresponding methods
     * Always use your module prefix to avoid conflicts with other modules
     * in future.
     *
     * @param \Priyx_App $app - returned by reference
     */
    public function register(\Priyx_App &$app)
    {
        $app->get('/example', 'get_index', [], get_class($this));
        $app->get('/example/protected', 'get_protected', [], get_class($this));
    }

    public function get_index(\Priyx_App $app)
    {
        return $app->render('mod_example_index');
    }

    public function get_protected(\Priyx_App $app)
    {
        // call $this->di['is_client_logged'] method to validate if client is logged in
        $this->di['is_client_logged'];

        return $app->render('mod_example_index', ['show_protected' => true]);
    }
}
