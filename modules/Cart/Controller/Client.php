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



namespace Priyx\Mod\Cart\Controller;

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
        $app->get('/cart', 'get_cart', [], get_class($this));
        $app->get('/checkout', 'get_checkout', [], get_class($this));
    }

    public function get_cart(\Priyx_App $app)
    {
        $orderform = $app->renderOrderform('viewcart');
        if ($orderform !== null) {
            return $orderform;
        }

        return $app->render('mod_cart_index');
    }

    public function get_checkout(\Priyx_App $app)
    {
        $orderform = $app->renderOrderform('checkout');
        if ($orderform !== null) {
            return $orderform;
        }

        return $app->render('mod_cart_index');
    }
}
