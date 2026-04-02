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



namespace Priyx\Mod\Servicelicense\Api;

/**
 *License Service management.
 */
class Client extends \Api_Abstract
{
    /**
     * Reset license validation rules.
     *
     * @param int $order_id - License service order id
     *
     * @return bool
     */
    public function reset($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->reset($s);
    }

    public function _getService(array $data)
    {
        $required = ['order_id' => 'Order id is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();

        $bindings = [
            ':id' => $data['order_id'],
            ':client_id' => $client->id,
        ];

        $order = $this->di['db']->findOne('ClientOrder', 'id = :id AND client_id = :client_id', $bindings);

        if (!$order instanceof \Model_ClientOrder) {
            throw new \Priyx_Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceLicense) {
            throw new \Priyx_Exception('Order is not activated');
        }

        return $s;
    }
}
