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
class Client extends \Api_Abstract
{
    /**
     * Change hosting account username.
     *
     * @param int    $order_id - Hosting account order id
     * @param string $username - New username
     *
     * @return bool
     */
    public function change_username($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountUsername($order, $s, $data);
    }

    /**
     * Change hosting account domain.
     *
     * @param int    $order_id         - Hosting account order id
     * @param string $password         - New second level domain name, ie: mydomain
     * @param string $password_confirm - New top level domain, ie: .com
     *
     * @return bool
     */
    public function change_domain($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountDomain($order, $s, $data);
    }

    /**
     * Change hosting account password.
     *
     * @param int    $order_id         - Hosting account order id
     * @param string $password         - New account password
     * @param string $password_confirm - Repeat new password
     *
     * @return bool
     */
    public function change_password($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountPassword($order, $s, $data);
    }

    /**
     * Get hosting plans pairs. Usually for select Priyx.
     *
     * @return array
     */
    public function hp_get_pairs($data)
    {
        return $this->getService()->getHpPairs();
    }

    public function _getService($data)
    {
        if (!isset($data['order_id'])) {
            throw new \Priyx_Exception('Order id is required');
        }
        $identity = $this->getIdentity();
        $order = $this->di['db']->findOne('ClientOrder', 'id = ? and client_id = ?', [$data['order_id'], $identity->id]);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \Priyx_Exception('Order not found');
        }

        $orderSerivce = $this->di['mod_service']('order');
        $s = $orderSerivce->getOrderService($order);
        if (!$s instanceof \Model_ServiceHosting) {
            throw new \Priyx_Exception('Order is not activated');
        }

        return [$order, $s];
    }
}
