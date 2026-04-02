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

namespace Priyx\Mod\Pterodactyl\Lib\Server\Manager;

class Pterodactyl extends \Server_Manager {

  public static function getForm() {
    return array(
      'label' => 'Pterodactyl Server Manager',
    );
  }

  public function getLoginUrl() {
    return $this->_config['panel_url'] ?? '';
  }

  public function getResellerLoginUrl() {
    return "https://pterodactyl.io";
  }

  public function testConnection() {
    try {
        list($response, $responseCode) = $this->_request("GET", "users");
        return $responseCode == 200;
    } catch (\Exception $e) {
        return false;
    }
  }

  private function _request($requestType, $endpoint, $bodyParams = array()) {
    $curl = curl_init();

    $header = array();
    $header[] = 'Authorization: Bearer ' . $this->getAPIKey();
    $header[] = 'Accept: application/vnd.pterodactyl.' . $this->getAPIVersion() . '+json';
    $header[] = 'Content-Type: application/json';

    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_URL, $this->getBaseURL() . $endpoint);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $dataRequestTypes = array(
      "POST",
      "PUT",
      "PATCH",
      "DELETE"
    );

    if(in_array($requestType, $dataRequestTypes)) {
      if($requestType == "POST") {
        curl_setopt($curl, CURLOPT_POST, 1);
      } else {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $requestType);
      }

      curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($bodyParams));
    }

    $response = curl_exec($curl);
    $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return array($response, $responseCode);
  }

  private function getAPIKey() {
    return $this->_config['accesshash'] ?? $this->_config['password'] ?? '';
  }

  private function getAPIVersion() {
    return $this->_config['api_version'] ?? 'v1';
  }

  private function getBaseURL() {
    $url = rtrim($this->_config['host'] ?? $this->_config['ip'] ?? '', '/');
    return $url . '/api/application/';
  }

  // Abstract methods implementation (stubs or basic logic)
  public function createAccount(\Server_Account $a) { return true; }
  public function synchronizeAccount(\Server_Account $a) { return $a; }
  public function suspendAccount(\Server_Account $a) { return true; }
  public function unsuspendAccount(\Server_Account $a) { return true; }
  public function cancelAccount(\Server_Account $a) { return true; }
  public function changeAccountPassword(\Server_Account $a, $new_password) { return true; }
  public function changeAccountUsername(\Server_Account $a, $new_username) { return true; }
  public function changeAccountDomain(\Server_Account $a, $new_domain) { return true; }
  public function changeAccountIp(\Server_Account $a, $new_ip) { return true; }
  public function changeAccountPackage(\Server_Account $a, \Server_Package $p) { return true; }
}
