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

namespace Priyx\Mod\Affiliate\Controller;

class Client implements \Priyx\InjectionAwareInterface
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

    public function register(\Priyx_App &$app)
    {
        $app->get('/client/affiliates', 'get_index', [], get_class($this));
        $app->get('/client/affiliate', 'get_index', [], get_class($this));
    }

    public function get_index(\Priyx_App $app)
    {
        $this->di['is_client_logged'];
        
        // Mock data for now
        $stats = [
            'clicks' => 0,
            'signups' => 0,
            'commissions' => '0.00',
        ];
        
        return $app->render('mod_affiliate_index', ['stats' => $stats]);
    }
}
