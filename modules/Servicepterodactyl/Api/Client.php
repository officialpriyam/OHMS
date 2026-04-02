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

namespace Priyx\Mod\Servicepterodactyl\Api;

class Client extends \Api_Abstract
{
    public function get_details($data)
    {
        $required = [
            'id' => 'Order ID required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->load('ClientOrder', $data['id']);
        if (!$order) {
            throw new \Priyx_Exception('Order not found');
        }

        // Verify ownership
        $identity = $this->getIdentity();
        if ($order->client_id != $identity->id) {
            throw new \Priyx_Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        
        if (!$service || !$service->pterodactyl_user_id) {
            return [];
        }

        $pterodactylUser = $this->getService()->getUser($service->pterodactyl_user_id);
        
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_servicepterodactyl');
        $panelUrl = rtrim($config['panel_url'] ?? '', '/');

        return [
            'user' => $pterodactylUser['attributes'] ?? null,
            'panel_url' => $panelUrl,
            'server_id' => $service->pterodactyl_server_id,
        ];
    }

    public function change_password($data)
    {
        $required = [
            'id' => 'Order ID required',
            'password' => 'Password required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->load('ClientOrder', $data['id']);
        if (!$order) {
            throw new \Priyx_Exception('Order not found');
        }

        // Verify ownership
        $identity = $this->getIdentity();
        if ($order->client_id != $identity->id) {
            throw new \Priyx_Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);

        if (!$service || !$service->pterodactyl_user_id) {
            throw new \Priyx_Exception('Service not provisioned');
        }

        $this->getService()->updatePassword($service->pterodactyl_user_id, $data['password']);

        return true;
    }
}
