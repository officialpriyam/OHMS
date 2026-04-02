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
 * This file connects OHMS admin area interface and API
 * Class does not extend any other class
 */

namespace Priyx\Mod\Servicepterodactyl\Controller;

class Admin implements \Priyx\InjectionAwareInterface {
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di) {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi() {
        return $this->di;
    }

    /**
     * This method registers menu items in admin area navigation block
     * This navigation is cached in bb-data/cache/{hash}. To see changes please
     * remove the file
     *
     * @return array
     */
    public function fetchNavigation()
    {
        return array(
            'group'  =>  array(
                'index'     => 1600,                // menu sort order
                'location'  =>  'servicepterodactyl',          // menu group identificator for subitems
                'label'     => 'Pterodactyl Module',    // menu group title
                'class'     => 'servicepterodactyl',           // used for css styling menu item
            ),
            'subpages'=> array(
                array(
                    'location'  => 'servicepterodactyl', // place this module in extensions group
                    'label'     => 'Pterodactyl Configuration',
                    'index'     => 1500,
                    'uri'       => $this->di['url']->adminLink('servicepterodactyl'),
                    'class'     => '',
                ),
            ),
        );
    }

    /**
     * Methods maps admin areas urls to corresponding methods
     * Always use your module prefix to avoid conflicts with other modules
     * in future
     *
     *
     * @example $app->get('/example/test',      'get_test', null, get_class($this)); // calls get_test method on this class
     * @example $app->get('/example/:id',        'get_index', array('id'=>'[0-9]+'), get_class($this));
     * @param \Priyx_App $app
     */
    public function register(\Priyx_App &$app) {
        $app->get('/servicepterodactyl',             'get_index', array(), get_class($this));
    }

    public function get_index(\Priyx_App $app) {
        $this->di['is_admin_logged'];
        return $app->render('mod_servicepterodactyl_index');
    }
}

