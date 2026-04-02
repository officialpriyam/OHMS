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

namespace Priyx\Mod\Servicepterodactyl;

class Service implements \Priyx\InjectionAwareInterface {

  protected $di;

  public function setDi($di) {
    $this->di = $di;
  }

  public function getDi() {
    return $this->di;
  }
  public function getNests() {
    return $this->_request('GET', 'nests');
  }

  public function getEggs($nestId) {
    return $this->_request('GET', "nests/{$nestId}/eggs");
  }

  public function getLocations() {
    return $this->_request('GET', 'locations');
  }

    public function create($order, $service = null)
    {
        $model = $this->di['db']->dispense('service_pterodactyl');
        if (!$model) {
            throw new \Priyx_Exception('Could not dispense service_pterodactyl model');
        }
        $model->client_id = $order->client_id;
        $model->config = $order->config;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function activate($order, $service = null)
    {
        if (!$service instanceof \RedBeanPHP\OODBBean) {
            // Try to load service if not provided
            $orderService = $this->di['mod_service']('order');
            $service = $orderService->getOrderService($order);
        }

        if (!$service) {
            throw new \Priyx_Exception('Order :id has no active service', [':id' => $order->id]);
        }

        $client = $this->di['db']->load('Client', $order->client_id);
        
        // 1. Get or Create User on Pterodactyl
        $pterodactylUser = $this->_getOrCreateUser($client);
        $pterodactylUserId = $pterodactylUser['attributes']['id'];

        // 2. Create Server
        $config = json_decode($order->config, true);
        $product = $this->di['db']->load('Product', $order->product_id);
        $productConfig = json_decode($product->config, true);

        // Merge product config with order config (order config takes precedence for selectable options)
        $mergedConfig = array_merge($productConfig, $config);

        $nestId = (int)($mergedConfig['nest_id'] ?? 0);
        $eggId = (int)($mergedConfig['egg_id'] ?? 0);

        // Fetch Egg Variables to get defaults
        $environment = [];
        if ($nestId && $eggId) {
            $eggData = $this->_request('GET', "nests/{$nestId}/eggs/{$eggId}?include=variables");
            if (isset($eggData['attributes']['relationships']['variables']['data'])) {
                foreach ($eggData['attributes']['relationships']['variables']['data'] as $variable) {
                    $envVar = $variable['attributes']['env_variable'];
                    $defaultValue = $variable['attributes']['default_value'];
                    $environment[$envVar] = $defaultValue;
                }
            }
        }

        // Merge with provided environment variables from config
        if (isset($mergedConfig['environment']) && is_array($mergedConfig['environment'])) {
            $environment = array_merge($environment, $mergedConfig['environment']);
        }

        // Support configurable options for resources
        $resources = [
            'memory' => (int)($mergedConfig['memory'] ?? 1024),
            'swap'   => (int)($mergedConfig['swap'] ?? 0),
            'disk'   => (int)($mergedConfig['disk'] ?? 5120),
            'io'     => (int)($mergedConfig['io'] ?? 500),
            'cpu'    => (int)($mergedConfig['cpu'] ?? 100),
            'databases' => (int)($mergedConfig['databases'] ?? 0),
            'allocations' => (int)($mergedConfig['allocations'] ?? 0),
            'backups' => (int)($mergedConfig['backups'] ?? 0),
        ];

        // If configurable options are present, they might override product defaults
        if(isset($config['config_options']) && is_array($config['config_options'])) {
            foreach($resources as $key => $val) {
                if(isset($config['config_options'][$key])) {
                    $resources[$key] = (int)$config['config_options'][$key];
                }
            }
        }

        $serverData = [
            'name' => $config['config_options']['hostname'] ?? ($order->title . ' #' . $order->id),
            'user' => $pterodactylUserId,
            'nest' => $nestId,
            'egg' => $eggId,
            'docker_image' => $mergedConfig['image'] ?? 'ghcr.io/pterodactyl/yolks:debian',
            'startup' => $mergedConfig['startup'] ?? '',
            'limits' => [
                'memory' => $resources['memory'],
                'swap' => $resources['swap'],
                'disk' => $resources['disk'],
                'io' => $resources['io'],
                'cpu' => $resources['cpu'],
            ],
            'feature_limits' => [
                'databases' => $resources['databases'],
                'allocations' => $resources['allocations'],
                'backups' => $resources['backups'],
            ],
            'deploy' => [
                'locations' => [(int)($mergedConfig['location_id'] ?? 0)],
                'dedicated_ip' => false,
                'port_range' => [],
            ],
            'environment' => $environment,
            'start_on_completion' => true,
        ];

        $response = $this->_request('POST', 'servers', $serverData);
        if (empty($response) || !isset($response['attributes']['id'])) {
            throw new \Priyx_Exception('Failed to create server on Pterodactyl');
        }

        $service->pterodactyl_server_id = $response['attributes']['id'];
        $service->pterodactyl_user_id = $pterodactylUserId;
        $service->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($service);

        return true;
    }

    public function suspend($order, $service = null)
    {
        if (!$service) {
            $orderService = $this->di['mod_service']('order');
            $service = $orderService->getOrderService($order);
        }

        if (!$service || !$service->pterodactyl_server_id) {
            return false;
        }

        return $this->_request('POST', "servers/{$service->pterodactyl_server_id}/suspend");
    }

    public function unsuspend($order, $service = null)
    {
        if (!$service) {
            $orderService = $this->di['mod_service']('order');
            $service = $orderService->getOrderService($order);
        }

        if (!$service || !$service->pterodactyl_server_id) {
            return false;
        }

        return $this->_request('POST', "servers/{$service->pterodactyl_server_id}/unsuspend");
    }

    public function cancel($order, $service = null)
    {
        if (!$service) {
            $orderService = $this->di['mod_service']('order');
            $service = $orderService->getOrderService($order);
        }

        if (!$service || !$service->pterodactyl_server_id) {
            return true;
        }

        $this->_request('DELETE', "servers/{$service->pterodactyl_server_id}");
        
        return true;
    }

    public function renew($order, $service = null)
    {
        return true;
    }

    public function getUser($userId)
    {
        return $this->_request('GET', 'users/' . $userId);
    }

    public function updatePassword($userId, $password)
    {
        $user = $this->getUser($userId);
        if (!$user) {
            throw new \Priyx_Exception('User not found on Pterodactyl');
        }

        $data = [
            'email' => $user['attributes']['email'],
            'username' => $user['attributes']['username'],
            'first_name' => $user['attributes']['first_name'],
            'last_name' => $user['attributes']['last_name'],
            'password' => $password,
        ];

        return $this->_request('PATCH', 'users/' . $userId, $data);
    }

    private function _getOrCreateUser(\Model_Client $client)
    {
        // Search by email
        $users = $this->_request('GET', 'users?filter[email]=' . urlencode($client->email));
        if (!empty($users)) {
            return $users[0];
        }

        // Create new user
        $userData = [
            'email' => $client->email,
            'username' => 'u_' . $client->id . '_' . bin2hex(random_bytes(4)),
            'first_name' => $client->first_name ?: 'Client',
            'last_name' => $client->last_name ?: '#' . $client->id,
        ];

        $user = $this->_request('POST', 'users', $userData);
        if (empty($user)) {
            throw new \Priyx_Exception('Failed to create user on Pterodactyl');
        }

        return $user;
    }

    private function _request($requestType, $endpoint, $bodyParams = array()) {
    $extensionService = $this->di['mod_service']('extension');
    $config = $extensionService->getConfig('mod_servicepterodactyl');
    
    $apiKey = $config['api_key'] ?? '';
    $apiVersion = $config['api_version'] ?? 'v1';
    $panelUrl = rtrim($config['panel_url'] ?? '', '/');

    if (empty($apiKey) || empty($panelUrl)) {
        return [];
    }

    $url = $panelUrl . '/api/application/' . $endpoint;
    
    $curl = curl_init();
    $header = array(
      'Authorization: Bearer ' . $apiKey,
      'Accept: application/vnd.pterodactyl.' . $apiVersion . '+json',
      'Content-Type: application/json'
    );

    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    if ($requestType !== 'GET') {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $requestType);
      if (!empty($bodyParams)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($bodyParams));
      }
    }

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $data = json_decode($response, true);

    if ($httpCode >= 400) {
        error_log("Pterodactyl API Error ($httpCode) on $endpoint: " . $response);
        return [];
    }

    // Pterodactyl lists are wrapped in 'data', single objects are not.
    if (isset($data['data']) && is_array($data['data'])) {
        return $data['data'];
    }

    return $data ?: [];
  }
}
