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




use Priyx\InjectionAwareInterface;

class Priyx_App {

    protected $mappings       = array();
    protected $before_filters = array();
    protected $after_filters  = array();
    protected $shared         = array();
    protected $options;
    protected $di             = NULL;
    protected $ext            = 'latte';
    protected $mod            = 'index';
    protected $url            = '/';

    public $uri = NULL;

    public function __construct($options=array())
    {
        $this->options = new ArrayObject($options);
    }

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function setUrl($url)
    {
        if (!empty($url) && $url[0] !== '/') {
            $url = '/' . $url;
        }
        $this->url = $url;
    }

    protected function registerModule()
    {
        //bind module urls and process
        //determine module and bind urls
        $requestUri = $this->url;
        if(empty($requestUri)) {
            $requestUri = '/';
        }
        if($requestUri == '/') {
            $mod = 'index';
        } else {
            $requestUri = trim($requestUri, '/');
            if(strpos($requestUri, '/') === false) {
                $mod = $requestUri;
            } else {
                list($mod) = explode('/',$requestUri);
            }
        }
        $mod = preg_replace('/[^\x20-\x7E]/', '', $mod);

        $this->mod = $mod;
        $this->uri = $requestUri;
    }

    protected function init(){}

    public function show404(Exception $e) {
        error_log($e->getMessage());
        header("HTTP/1.0 404 Not Found");
        return $this->render('404', array('exception'=>$e));
    }

    /**
     * @param string $url
     * @param string $methodName
     * @param string $class
     */
    public function get($url, $methodName, $conditions = array(), $class = null) {
       $this->event('get', $url, $methodName, $conditions, $class);
    }

    /**
     * @param string $url
     * @param string $methodName
     * @param string $class
     */
    public function post($url, $methodName, $conditions = array(), $class = null) {
       $this->event('post', $url, $methodName, $conditions, $class);
    }

    public function put($url, $methodName, $conditions = array(), $class = null) {
       $this->event('put', $url, $methodName, $conditions, $class);
    }

    public function delete($url, $methodName, $conditions = array(), $class = null) {
       $this->event('delete', $url, $methodName, $conditions, $class);
    }

    public function before($methodName, $filterName) {
        $this->push_filter($this->before_filters, $methodName, $filterName);
    }

    public function after($methodName, $filterName) {
        $this->push_filter($this->after_filters, $methodName, $filterName);
    }

    protected function push_filter(&$arr_filter, $methodName, $filterName) {
        if (!is_array($methodName)) {
            $methodName = explode('|', $methodName);
        }

        $counted = count($methodName);
        for ($i = 0; $i < $counted; $i++) {
            $method = $methodName[$i];
            if (!isset($arr_filter[$method])) {
                $arr_filter[$method] = array();
            }
            array_push($arr_filter[$method], $filterName);
        }
    }

    protected function run_filter($arr_filter, $methodName) {
        if(isset($arr_filter[$methodName])) {
            $counted = count($arr_filter[$methodName]);
            for ($i=0; $i < $counted; $i++) {
                $return = call_user_func(array($this, $arr_filter[$methodName][$i]));

                if(!is_null($return)) {
                    return $return;
                }
            }
        }
    }

    public function run()
    {
        $this->registerModule();
        $this->init();
        
        // System License Check
        try {
            $db = $this->di['db'];
            $crypt = $this->di['crypt'];

            $settings = $db->getAssoc("SELECT param, value FROM setting WHERE param IN ('license_key', 'license_product', 'device_id', 'license_status', 'license_last_check')");
            $configLicense = $this->getLicenseFromConfig();

            $licenseKey = null;
            $status = 'expired';
            $lastCheck = 0;
            $productSlug = 'ohms';
            $deviceId = '';

            if (isset($settings['license_key']) && $settings['license_key'] !== '') {
                try {
                    $licenseKey = $crypt->decrypt($settings['license_key']);
                } catch (\Exception $e) {
                    // Legacy/plain value fallback.
                    $licenseKey = $settings['license_key'];
                }
                $status = $settings['license_status'] ?? 'expired';
                $lastCheck = (int) ($settings['license_last_check'] ?? 0);
                $productSlug = $settings['license_product'] ?? 'ohms';
                $deviceId = $settings['device_id'] ?? '';
            } elseif (!empty($configLicense['license_key'])) {
                // Allow bootstrapping from config.php when DB settings are missing.
                $licenseKey = $configLicense['license_key'];
                $status = $configLicense['license_status'] ?? 'expired';
                $lastCheck = (int) ($configLicense['license_last_check'] ?? 0);
                $productSlug = $configLicense['license_product'] ?? 'ohms';
                $deviceId = $configLicense['device_id'] ?? '';
            }

            if (!empty($licenseKey)) {
                // Reverify every 7 days (604800 seconds)
                if (time() - $lastCheck > 604800) {
                    $response = $this->callLicenseApi('verify', [
                        'license_key' => $licenseKey,
                        'product_slug' => $productSlug,
                    ]);
                    $responseData = $response['data'];
                    $httpCode = $response['http_code'];

                    if ($httpCode === 200 && !empty($responseData['status'])) {
                        $status = $responseData['status']; // e.g. 'active', 'suspended', 'expired'
                    } elseif ($httpCode === 200 && isset($responseData['activated'])) {
                        $status = $responseData['activated'] ? 'active' : 'suspended';
                    } elseif ($httpCode >= 400 && is_array($responseData)) {
                        $status = 'suspended';
                    } else {
                        error_log('License verification skipped due to unexpected response from '.$response['url'].' (HTTP '.$httpCode.').');
                    }

                    $lastCheck = time();
                }

                // Keep license persisted in DB settings.
                $this->syncLicenseToSettings($db, $crypt, [
                    'license_key' => $licenseKey,
                    'license_product' => $productSlug,
                    'device_id' => $deviceId,
                    'license_status' => $status,
                    'license_last_check' => $lastCheck,
                ]);

                // Keep license persisted in config.php as requested.
                $this->persistLicenseToConfig([
                    'license_key' => $licenseKey,
                    'license_product' => $productSlug,
                    'device_id' => $deviceId,
                    'license_status' => $status,
                    'license_last_check' => $lastCheck,
                ]);

                if ($status !== 'active') {
                    die('<html><body style="font-family:sans-serif; text-align:center; padding:50px;"><h2>License Error</h2><p>Your OHMS license is expired or suspended. Contact support.</p><p>URL: <a href="http://priyxstudio.in/license/">http://priyxstudio.in/license/</a></p></body></html>');
                }
            } else {
                // If the system is installed but missing a license key entirely (unlicensed)
                die('<html><body style="font-family:sans-serif; text-align:center; padding:50px;"><h2>License Required</h2><p>This installation of OHMS is unlicensed. Please obtain a valid license key.</p></body></html>');
            }
        } catch (\Exception $e) {
            // DB not initialized yet (e.g. during very early CLI install phase)
        }

        return $this->processRequest();
    }

    /**
     * @param string $path
     */
    public function redirect($path)
    {
        $location = $this->di['url']->link($path);
        header("Location: $location");
        exit;
    }

    /**
     * @param string $fileName
     */
    public function render($fileName, $variableArray = array())
    {
        print 'Rendering '.$fileName;
    }

    public function sendFile($filename, $contentType, $path) {
        header("Content-type: $contentType");
        header("Content-Disposition: attachment; filename=$filename");
        return readfile($path);
    }

    public function sendDownload($filename, $path) {
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=$filename".";");
        header("Content-Transfer-Encoding: binary");
        return readfile($path);
    }

    protected function callLicenseApi($endpoint, array $payload)
    {
        $urls = [
            'https://priyxstudio.in/license/api/'.$endpoint,
            'http://priyxstudio.in/license/api/'.$endpoint,
        ];

        $lastResult = [
            'data' => null,
            'body' => '',
            'http_code' => 0,
            'error' => '',
            'url' => '',
        ];

        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            if (0 === strpos($url, 'https://')) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }

            $body = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (false === $body) {
                $body = '';
            }

            $data = json_decode($body, true);
            if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
                $data = null;
            }

            $lastResult = [
                'data' => $data,
                'body' => $body,
                'http_code' => $httpCode,
                'error' => $error,
                'url' => $url,
            ];

            if (is_array($data)) {
                return $lastResult;
            }
        }

        return $lastResult;
    }

    protected function getLicenseFromConfig()
    {
        $config = $this->di['config'];
        $nested = [];
        if (isset($config['license']) && is_array($config['license'])) {
            $nested = $config['license'];
        }

        $key = $nested['key'] ?? ($nested['license_key'] ?? ($config['license_key'] ?? null));
        $product = $nested['product'] ?? ($nested['license_product'] ?? ($config['license_product'] ?? 'ohms'));
        $deviceId = $nested['device_id'] ?? ($config['device_id'] ?? '');
        $status = $nested['status'] ?? ($nested['license_status'] ?? ($config['license_status'] ?? 'expired'));
        $lastCheck = $nested['last_check'] ?? ($nested['license_last_check'] ?? ($config['license_last_check'] ?? 0));

        if (is_string($key)) {
            $key = trim($key);
        }

        return [
            'license_key' => $key,
            'license_product' => $product ?: 'ohms',
            'device_id' => (string) $deviceId,
            'license_status' => $status ?: 'expired',
            'license_last_check' => (int) $lastCheck,
        ];
    }

    protected function syncLicenseToSettings($db, $crypt, array $license)
    {
        $encryptedLicenseKey = $crypt->encrypt($license['license_key']);

        $this->upsertSetting($db, 'license_key', $encryptedLicenseKey);
        $this->upsertSetting($db, 'license_product', (string) $license['license_product']);
        $this->upsertSetting($db, 'device_id', (string) $license['device_id']);
        $this->upsertSetting($db, 'license_status', (string) $license['license_status']);
        $this->upsertSetting($db, 'license_last_check', (string) ((int) $license['license_last_check']));
    }

    protected function upsertSetting($db, $param, $value)
    {
        $exists = (int) $db->getCell('SELECT COUNT(id) FROM setting WHERE param = :param', [':param' => $param]);
        if ($exists > 0) {
            $db->exec(
                'UPDATE setting SET value = :value, updated_at = :updated_at WHERE param = :param',
                [':value' => $value, ':updated_at' => date('Y-m-d H:i:s'), ':param' => $param]
            );

            return;
        }

        $db->exec(
            'INSERT INTO setting (param, value, created_at, updated_at) VALUES (:param, :value, :created_at, :updated_at)',
            [
                ':param' => $param,
                ':value' => $value,
                ':created_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    protected function persistLicenseToConfig(array $license)
    {
        $configPath = PS_PATH_ROOT.'/config.php';
        if (!is_file($configPath) || !is_writable($configPath)) {
            return;
        }

        $config = @include $configPath;
        if (!is_array($config)) {
            return;
        }

        $newLicenseConfig = [
            'key' => (string) $license['license_key'],
            'product' => (string) $license['license_product'],
            'device_id' => (string) $license['device_id'],
            'status' => (string) $license['license_status'],
            'last_check' => (int) $license['license_last_check'],
        ];

        $currentLicenseConfig = [];
        if (isset($config['license']) && is_array($config['license'])) {
            $currentLicenseConfig = [
                'key' => (string) ($config['license']['key'] ?? ($config['license']['license_key'] ?? '')),
                'product' => (string) ($config['license']['product'] ?? ($config['license']['license_product'] ?? 'ohms')),
                'device_id' => (string) ($config['license']['device_id'] ?? ''),
                'status' => (string) ($config['license']['status'] ?? ($config['license']['license_status'] ?? 'expired')),
                'last_check' => (int) ($config['license']['last_check'] ?? ($config['license']['license_last_check'] ?? 0)),
            ];
        }

        if ($currentLicenseConfig === $newLicenseConfig) {
            return;
        }

        $config['license'] = $newLicenseConfig;
        $export = "<?php\nreturn ".var_export($config, true).";\n";
        @file_put_contents($configPath, $export, LOCK_EX);
    }

    protected function executeShared($classname, $methodName, $params)
    {
        $class = new $classname();
        if($class instanceof InjectionAwareInterface) {
            $class->setDi($this->di);
        }
        $reflection = new ReflectionMethod(get_class($class), $methodName);
        $args = array();
        $args[] = $this; // first param always app instance

        foreach($reflection->getParameters() as $param) {
            if(isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            }
            else if($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        return $reflection->invokeArgs($class, $args);
    }

    protected function execute($methodName, $params, $classname = null) {
        $return = $this->run_filter($this->before_filters, $methodName);
        if (!is_null($return)) {
          return $return;
        }

        $reflection = new ReflectionMethod(get_class($this), $methodName);
        $args = array();

        foreach($reflection->getParameters() as $param) {
            if(isset($params[$param->name])) {
                $args[$param->name] = $params[$param->name];
            }
            else if($param->isDefaultValueAvailable()) {
                $args[$param->name] = $param->getDefaultValue();
            }
        }

        $response = $reflection->invokeArgs($this, $args);

        $return = $this->run_filter($this->after_filters, $methodName);
        if (!is_null($return)) {
          return $return;
        }

        return $response;
    }

    /**
     * @param string $httpMethod
     */
    protected function event($httpMethod, $url, $methodName, $conditions=array(), $classname = null) {
        if (method_exists($this, $methodName)) {
            array_push($this->mappings, array($httpMethod, $url, $methodName, $conditions));
        }
        if (null !== $classname) {
            array_push($this->shared, array($httpMethod, $url, $methodName, $conditions, $classname));
        }
    }

    /**
     * Check if the requested URL is in the allowlist.
     * 
     * @since 4.22.0
     */
    protected function checkAllowedURLs()
    {
        $REQUEST_URI = $this->di['request']->getServer('REQUEST_URI');
        $allowedURLs = $this->di['config']['maintenance_mode']['allowed_urls'];
        $rootUrl = $this->di['config']['url'];
        
        // Allow access to the staff panel all the time
        $adminApiPrefixes = [
            "/api/guest/staff/login",
            "/api/admin",
        ];

        foreach ($adminApiPrefixes as $adminApiPrefix){
            $realAdminApiUrl = $rootUrl [-1] === '/' ? substr($rootUrl, 0 ,-1).$adminApiPrefix : $rootUrl.$adminApiPrefix;
            $allowedURLs[] = parse_url($realAdminApiUrl)['path'];
        }
        foreach ($allowedURLs as $url){
            if (preg_match('/^'.str_replace('/','\/', $url).'(.*)/', $REQUEST_URI) !== 0){
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the visitor IP is in the allowlist.
     * 
     * @since 4.22.0
     */
    protected function checkAllowedIPs()
    {
        $allowedIPs  = $this->di['config']['maintenance_mode']['allowed_ips'];
        $visitorIP = $this->di['request']->getClientAddress();

        // Check if the visitor is in using of the allowed IPs/networks
        foreach ($allowedIPs as $network)
        {
            if(strpos($network, '/') == false){$network .= '/32';}
            list($network, $netmask) = explode('/', $network, 2);
            $network_decimal = ip2long($network);
            $ip_decimal = ip2long($visitorIP);
            $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
            $netmask_decimal = ~ $wildcard_decimal;
            if(($ip_decimal & $netmask_decimal) == ($network_decimal & $netmask_decimal)){
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the requested URL is a part of the admin area.
     * 
     * @since 4.22.0
     */
    protected function checkAdminPrefix()
    {
        $REQUEST_URI = $this->di['request']->getServer('REQUEST_URI');
        $adminPrefix = $this->di['config']['admin_area_prefix'];
        $rootUrl = $this->di['config']['url'];

        $realAdminUrl = $rootUrl[-1] === '/' ? substr($rootUrl, 0 ,-1).$adminPrefix : $rootUrl.$adminPrefix;
        $realAdminPath = parse_url($realAdminUrl)['path'];
        
        if (preg_match('/^'.str_replace('/','\/', $realAdminPath).'(.*)/', $REQUEST_URI) !== 0) {
            return false;
        }
        return true;
    }

    protected function processRequest()
    {
        /**
         * Block requests if the system is undergoing maintenance.
         * It will respect any URL/IP whitelisting under the configuration file.
         * 
         * @since 4.22.0
         */
        if($this->di['config']['maintenance_mode']['enabled'] === true)
        {
            // Check the allowlists
            if($this->checkAdminPrefix() && $this->checkAllowedURLs() && $this->checkAllowedIPs()) {
                // Set response code to 503.
                header("HTTP/1.0 503 Service Unavailable");

                if($this->mod == "api") {
                    $exc = new \Priyx_Exception('The system is undergoing maintenance. Please try again later.', [], 503);
                    return (new \Priyx\Mod\Api\Controller\Client())->renderJson(null, $exc);
                } else {
                    return $this->render('mod_system_maintenance');
                }
            }
        }

        $sharedCount = count($this->shared);
        for($i = 0; $i < $sharedCount; $i++) {
            $mapping = $this->shared[$i];
            $url = new Priyx_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if($url->match) {
                return $this->executeShared($mapping[4], $mapping[2], $url->params);
            }
        }

        // this class mappings
        $mappingsCount = count($this->mappings);
        for($i = 0; $i < $mappingsCount; $i++) {
            $mapping = $this->mappings[$i];
            $url = new Priyx_UrlHelper($mapping[0], $mapping[1], $mapping[3], $this->url);
            if($url->match) {
                return $this->execute($mapping[2], $url->params);
            }
        }

        $e = new \Priyx_Exception('Page :url not found', array(':url'=>$this->url), 404);
        return $this->show404($e);
    }

    /**
     * @deprecated
     */
    public function getApiAdmin()
    {
        return $this->di['api_admin'];
    }

    /**
     * @deprecated
     */
    public function getApiClient()
    {
        return $this->di['api_client'];
    }

    /**
     * @deprecated
     */
    public function getApiGuest()
    {
        return $this->di['api_guest'];
    }
}
