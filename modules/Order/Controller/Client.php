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



namespace Priyx\Mod\Order\Controller;

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
        $app->get('/order', 'get_products', [], get_class($this));
        $app->get('/order/service', 'get_orders', [], get_class($this));
        $app->get('/order/:id', 'get_configure_product', ['id' => '[0-9]+'], get_class($this));
        $app->get('/order/:slug', 'get_configure_product_by_slug', ['slug' => '[a-z0-9-]+'], get_class($this));
        $app->get('/order/service/manage/:id', 'get_order', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_products(\Priyx_App $app)
    {
        $orderform = $app->renderOrderform('products');
        if ($orderform !== null) {
            return $orderform;
        }

        return $app->render('mod_order_index');
    }

    public function get_configure_product_by_slug(\Priyx_App $app, $slug)
    {
        $api = $this->di['api_guest'];
        $product = $api->product_get(['slug' => $slug]);

        $screen = $product['type'] === 'domain' ? 'configureproductdomain' : 'configureproduct';
        $orderform = $app->renderOrderform($screen, ['product' => $product], ['product' => $product]);
        if ($orderform !== null) {
            return $orderform;
        }

        $tpl = 'mod_service'.$product['type'].'_order';
        if ($api->system_template_exists(['file' => $tpl.'.latte'])) {
            return $app->render($tpl, ['product' => $product]);
        }
        if ($api->system_template_exists(['file' => $tpl.'.phtml'])) {
            return $app->render($tpl, ['product' => $product], 'phtml');
        }

        return $app->render('mod_order_product', ['product' => $product]);
    }

    public function get_configure_product(\Priyx_App $app, $id)
    {
        $api = $this->di['api_guest'];
        $product = $api->product_get(['id' => $id]);

        $screen = $product['type'] === 'domain' ? 'configureproductdomain' : 'configureproduct';
        $orderform = $app->renderOrderform($screen, ['product' => $product], ['product' => $product]);
        if ($orderform !== null) {
            return $orderform;
        }

        $tpl = 'mod_service'.$product['type'].'_order';
        if ($api->system_template_exists(['file' => $tpl.'.latte'])) {
            return $app->render($tpl, ['product' => $product]);
        }
        if ($api->system_template_exists(['file' => $tpl.'.phtml'])) {
            return $app->render($tpl, ['product' => $product], 'phtml');
        }

        return $app->render('mod_order_product', ['product' => $product]);
    }

    public function get_orders(\Priyx_App $app)
    {
        $this->di['is_client_logged'];

        return $app->render('mod_order_list');
    }

    public function get_order(\Priyx_App $app, $id)
    {
        $api = $this->di['api_client'];
        $data = [
            'id' => $id,
        ];
        $order = $api->order_get($data);

        return $app->render('mod_order_manage', ['order' => $order]);
    }
}
