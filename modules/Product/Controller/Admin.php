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



namespace Priyx\Mod\Product\Controller;

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
            'group' => [
                'index' => 401,
                'location' => 'products',
                'label' => 'Products',
                'uri' => $this->di['url']->adminLink('products'),
                'class' => 'pic',
                'sprite_class' => 'dark-sprite-icon sprite-blocks',
            ],
            'subpages' => [
                [
                    'location' => 'products',
                    'index' => 110,
                    'label' => 'Products / Services',
                    'uri' => $this->di['url']->adminLink('product'),
                    'class' => '',
                ],
                [
                    'location' => 'products',
                    'index' => 120,
                    'label' => 'Product addons',
                    'uri' => $this->di['url']->adminLink('product/addons'),
                    'class' => '',
                ],
                [
                    'location' => 'products',
                    'index' => 130,
                    'label' => 'Product promotions',
                    'uri' => $this->di['url']->adminLink('product/promos'),
                    'class' => '',
                ],
                [
                    'location' => 'products',
                    'index' => 140,
                    'label' => 'Configurable options',
                    'uri' => $this->di['url']->adminLink('product/options'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Priyx_App &$app)
    {
        $app->get('/product', 'get_index', [], get_class($this));
        $app->get('/product/promos', 'get_promos', [], get_class($this));
        $app->get('/product/promo/:id', 'get_promo', ['id' => '[0-9]+'], get_class($this));
        $app->get('/product/manage/:id', 'get_manage', ['id' => '[0-9]+'], get_class($this));
        $app->get('/product/addons', 'get_addons', [], get_class($this));
        $app->get('/product/addon/:id', 'get_addon_manage', ['id' => '[0-9]+'], get_class($this));
        $app->get('/product/category/:id', 'get_cat_manage', ['id' => '[0-9]+'], get_class($this));
        $app->get('/product/options', 'get_options', [], get_class($this));
        $app->get('/product/options/group/:id', 'get_option_group', ['id' => '[0-9]+'], get_class($this));
        $app->get('/product/options/group/:group_id/option/new', 'get_option_new', ['group_id' => '[0-9]+'], get_class($this));
        $app->get('/product/options/group/:group_id/option/:id', 'get_option_manage', ['group_id' => '[0-9]+', 'id' => '[0-9]+'], get_class($this));
    }

    public function get_index(\Priyx_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_product_index');
    }

    public function get_addons(\Priyx_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_product_addons');
    }

    public function get_addon_manage(\Priyx_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $addon = $api->product_addon_get(['id' => $id]);

        return $app->render('mod_product_addon_manage', ['addon' => $addon, 'product' => $addon]);
    }

    public function get_cat_manage(\Priyx_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $cat = $api->product_category_get(['id' => $id]);

        return $app->render('mod_product_category', ['category' => $cat]);
    }

    public function get_manage(\Priyx_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $product = $api->product_get(['id' => $id]);
        $configurableGroups = $api->product_configurable_group_get_list([]);

        $addons = [];
        foreach ($product['addons'] as $addon) {
            $addons[] = $addon['id'];
        }

        $assignedConfigurableGroups = [];
        foreach (($product['configurable_options'] ?? []) as $group) {
            $assignedConfigurableGroups[] = $group['id'];
        }

        return $app->render('mod_product_manage', [
            'product' => $product,
            'assigned_addons' => $addons,
            'configurable_groups' => $configurableGroups,
            'assigned_configurable_groups' => $assignedConfigurableGroups,
        ]);
    }

    public function get_promo(\Priyx_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $promo = $api->product_promo_get(['id' => $id]);

        return $app->render('mod_product_promo', ['promo' => $promo]);
    }

    public function get_promos(\Priyx_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_product_promos');
    }

    public function get_options(\Priyx_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_product_options');
    }

    public function get_option_group(\Priyx_App $app, $id)
    {
        $this->di['is_admin_logged'];
        $api = $this->di['api_admin'];
        $group = $api->product_configurable_group_get(['id' => $id]);
        $productPairs = $api->product_get_pairs(['active_only' => false, 'products_only' => true]);

        return $app->render('mod_product_option_group', [
            'group' => $group,
            'product_pairs' => $productPairs,
        ]);
    }

    public function get_option_new(\Priyx_App $app, $group_id)
    {
        $this->di['is_admin_logged'];
        $api = $this->di['api_admin'];
        $group = $api->product_configurable_group_get(['id' => $group_id]);
        $pricingPeriods = $api->product_configurable_option_pricing_periods([]);
        $emptyPricing = [];
        foreach ($pricingPeriods as $code => $label) {
            $emptyPricing[$code] = 0;
        }

        return $app->render('mod_product_option_manage', [
            'group' => $group,
            'option' => [
                'id' => null,
                'group_id' => $group['id'],
                'title' => '',
                'description' => '',
                'type' => 'dropdown',
                'required' => false,
                'hidden' => false,
                'sort_order' => 0,
                'pricing' => $emptyPricing,
                'values' => [],
            ],
            'option_types' => $api->product_configurable_option_types([]),
            'pricing_periods' => $pricingPeriods,
            'pricing_period_codes' => array_keys($pricingPeriods),
        ]);
    }

    public function get_option_manage(\Priyx_App $app, $group_id, $id)
    {
        $this->di['is_admin_logged'];
        $api = $this->di['api_admin'];
        $group = $api->product_configurable_group_get(['id' => $group_id]);
        $option = $api->product_configurable_option_get(['id' => $id]);

        return $app->render('mod_product_option_manage', [
            'group' => $group,
            'option' => $option,
            'option_types' => $api->product_configurable_option_types([]),
            'pricing_periods' => $api->product_configurable_option_pricing_periods([]),
            'pricing_period_codes' => array_keys($api->product_configurable_option_pricing_periods([])),
        ]);
    }
}
