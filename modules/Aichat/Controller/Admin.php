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

namespace Priyx\Mod\Aichat\Controller;

class Admin implements \Priyx\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function fetchNavigation()
    {
        return [
            'group' => [
                'index' => 500,
                'location' => 'system',
                'label' => 'Settings',
                'class' => 'settings',
            ],
            'sub' => [
                [
                    'location' => 'system',
                    'label' => 'AI Chat Support',
                    'index' => 1500,
                    'uri' => $this->di['url']->adminLink('aichat'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Priyx_App &$app)
    {
        $app->get('/aichat', 'get_index', [], get_class($this));
    }

    public function get_index(\Priyx_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_aichat_settings');
    }
}
