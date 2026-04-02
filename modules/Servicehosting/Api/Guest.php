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



namespace Priyx\Mod\Servicehosting\Api;

/**
 * Hosting service management.
 */
class Guest extends \Api_Abstract
{
    /**
     * @param array $data
     *
     * @return array
     *
     * @throws \Priyx_Exception
     */
    public function free_tlds($data = [])
    {
        $required = [
            'product_id' => 'Product id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $product_id = $this->di['array_get']($data, 'product_id', 0);
        $product = $this->di['db']->getExistingModelById('Product', $product_id, 'Product was not found');

        if (\Model_Product::HOSTING !== $product->type) {
            throw new \Priyx_Exception('Product type is invalid');
        }

        return $this->getService()->getFreeTlds($product);
    }
}
