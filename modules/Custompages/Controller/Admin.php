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



namespace Priyx\Mod\Custompages\Controller;

class Admin implements \Priyx\InjectionAwareInterface
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

    public function fetchNavigation()
    {
        return [
            'subpages' => [
                [
                    'location' => 'extensions',
                    'label' => 'Custom Pages',
                    'index' => 2000,
                    'uri' => $this->di['url']->adminLink('custompages'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Priyx_App &$app)
    {
        $app->get('/custompages', 'get_index', [], get_class($this));
        $app->get('/custompages/:id', 'get_page', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_index(\Priyx_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_custompages_index');
    }

    public function get_page(\Priyx_App $app, $id)
    {
        return $app->render('mod_custompages_page', ['page_id' => $id]);
    }
}
