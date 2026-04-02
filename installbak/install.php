<?php
/**
 * @return bool
 * @see http://stackoverflow.com/a/2886224/2728507
 */
function isSSL()
{
    return
        (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS'])
        || 443 == $_SERVER['SERVER_PORT'];
}

date_default_timezone_set('UTC');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
ini_set('log_errors', '1');
ini_set('error_log', dirname(__FILE__) . '/logs/php_error.log');

$protocol = isSSL() ? 'https' : 'http';
$url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$current_url = pathinfo($url, PATHINFO_DIRNAME);
$root_url = str_replace('/install', '', $current_url) . '/';

define('PS_URL', $root_url);
define('PS_URL_INSTALL', PS_URL . 'install/');
define('PS_URL_ADMIN', PS_URL . 'index.php?_url=/ohms-admin');

define('PS_PATH_ROOT', realpath(dirname(__FILE__) . '/..'));
define('PS_PATH_LIBRARY', PS_PATH_ROOT . '/library');
define('PS_PATH_VENDOR', PS_PATH_ROOT . '/ps-vendor');
define('PS_PATH_INSTALL_THEMES', PS_PATH_ROOT . '/install');
define('PS_PATH_THEMES', PS_PATH_ROOT . '/themes');
define('PS_PATH_LICENSE', PS_PATH_ROOT . '/LICENSE');
define('PS_PATH_SQL', PS_PATH_ROOT . '/install/sql/structure.sql');
define('PS_PATH_SQL_DATA', PS_PATH_ROOT . '/install/sql/content.sql');
define('PS_PATH_INSTALL', PS_PATH_ROOT . '/install');
define('PS_PATH_CONFIG', PS_PATH_ROOT . '/config.php');
define('PS_PATH_CRON', PS_PATH_ROOT . '/cron.php');
define('PS_PATH_LANGS', PS_PATH_ROOT . '/locale');

/* 
  Config paths & templates
*/
define('PS_PATH_HTACCESS', PS_PATH_ROOT . '/.htaccess');
define('PS_PATH_HTACCESS_TEMPLATE', PS_PATH_ROOT . '/.htaccess.txt');

define('PS_BS_CONFIG', PS_PATH_THEMES . '/bootstrap/config/settings_data.json');
define('PS_BS_CONFIG_TEMPLATE', PS_PATH_THEMES . '/bootstrap/config/settings_data.json.txt');

define('PS_HURAGA_CONFIG', PS_PATH_THEMES . '/huraga/config/settings_data.json');
define('PS_HURAGA_CONFIG_TEMPLATE', PS_PATH_THEMES . '/huraga/config/settings_data.json.txt');

define('PS_BBTHEME_CONFIG', PS_PATH_THEMES . '/OHMS/config/settings_data.json');
define('PS_BBTHEM_CONFIG_TEMPLATE', PS_PATH_THEMES . '/OHMS/config/settings_data.json.txt');

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, [
    PS_PATH_LIBRARY,
    get_include_path(),
]));

require PS_PATH_VENDOR . '/autoload.php';

final class Priyx_Installer
{
    private $session;

    public function __construct()
    {
        include 'session.php';
        $this->session = new Session();
    }

    public function run($action)
    {
        switch ($action) {
            case 'check-db':
                $user = $_POST['db_user'];
                $host = $_POST['db_host'];
                $port = $_POST['db_port'];
                $pass = $_POST['db_pass'];
                $name = $_POST['db_name'];
                if (!$this->canConnectToDatabase($host, $port, $name, $user, $pass)) {
                    print 'Could not connect to database. Please check database details. You might need to create database first.';
                } else {
                    $this->session->set('db_host', $host);
                    $this->session->set('db_port', $port);
                    $this->session->set('db_name', $name);
                    $this->session->set('db_user', $user);
                    $this->session->set('db_pass', $pass);
                    print 'ok';
                }

                break;

            case 'install':
                try {
                    // Initializing database connection
                    $user = $_POST['db_user'];
                    $host = $_POST['db_host'];
                    $port = $_POST['db_port'];
                    $pass = $_POST['db_pass'];
                    $name = $_POST['db_name'];
                    if (!$this->canConnectToDatabase($host, $port, $name, $user, $pass)) {
                        throw new Exception('Could not connect to the database, or the database does not exist');
                    } else {
                        $this->session->set('db_host', $host);
                        $this->session->set('db_port', $port);
                        $this->session->set('db_name', $name);
                        $this->session->set('db_user', $user);
                        $this->session->set('db_pass', $pass);
                    }

                    // Configuring administrator's account
                    $admin_email = $_POST['admin_email'];
                    $admin_pass = $_POST['admin_pass'];
                    $admin_name = $_POST['admin_name'];
                    if (!$this->isValidAdmin($admin_email, $admin_pass, $admin_name)) {
                        throw new Exception('Administrator\'s account is not valid');
                    } else {
                        $this->session->set('admin_email', $admin_email);
                        $this->session->set('admin_pass', $admin_pass);
                        $this->session->set('admin_name', $admin_name);
                    }

                    $this->session->set('license', "OHMS CE");
                    $this->makeInstall($this->session);
                    $this->generateEmailTemplates();
                    session_destroy();
                    // Try to remove install folder
                    function rmAllDir($dir) {
                        if (is_dir($dir)) {
                          $contents = scandir($dir);
                          foreach ($contents as $content) {
                            if ($content != '.' && $content != '..') {
                              if (filetype($dir.'/'.$content) == 'dir') {
                                rmAllDir($dir.'/'.$content); 
                              }
                              else {
                                unlink($dir.'/'.$content);
                              }
                            }
                          }
                          reset($contents);
                          rmdir($dir);
                        }
                    }
                    try {
                        rmAllDir('../install');
                    }
                    catch(Exception $e) {
                        // do nothing
                    }
                    print 'ok';
                } catch (Exception $e) {
                    print $e->getMessage();
                }
                break;

            case 'index':
            default:
                // $this->session->set('agree', true); // Removed hardcoded agreement

                $se = new Priyx_Requirements();
                $options = $se->getOptions();
                $vars = [
                    'tos' => $this->getLicense(),

                    'folders' => $se->folders(),
                    'files' => $se->files(),
                    'os' => PHP_OS,
                    'os_ok' => true,
                    'Priyx_ver' => Priyx_Version::VERSION,
                    'Priyx_ver_ok' => $se->isPriyxVersionOk(),
                    'php_ver' => $options['php']['version'],
                    'php_ver_req' => $options['php']['min_version'],
                    'php_safe_mode' => $options['php']['safe_mode'],
                    'php_ver_ok' => $se->isPhpVersionOk(),
                    'extensions' => $se->extensions(),
                    'all_ok' => $se->canInstall(),

                    'db_host' => $this->session->get('db_host'),
                    'db_port' => $this->session->get('db_port'),
                    'db_name' => $this->session->get('db_name'),
                    'db_user' => $this->session->get('db_user'),
                    'db_pass' => $this->session->get('db_pass'),

                    'admin_email' => $this->session->get('admin_email'),
                    'admin_pass' => $this->session->get('admin_pass'),
                    'admin_name' => $this->session->get('admin_name'),

                    'license' => $this->session->get('license'),
                    'agree' => $this->session->get('agree'),

                    'install_module_path' => PS_PATH_INSTALL,
                    'cron_path' => PS_PATH_CRON,
                    'config_file_path' => PS_PATH_CONFIG,
                    'live_site' => PS_URL,
                    'admin_site' => PS_URL_ADMIN,

                    'domain' => pathinfo(PS_URL, PATHINFO_BASENAME),
                ];
                print $this->render('./assets/install.phtml', $vars);
                break;
        }
    }

    private function render($name, $vars = [])
    {
        $templatePath = $this->resolveTemplatePath($name);
        $layoutPath = $this->resolveTemplatePath('./assets/layout.phtml');

        $content = $this->renderTemplateFile($templatePath, $vars);

        return $this->renderTemplateFile($layoutPath, array_merge($vars, [
            'content' => $content,
            'version' => Priyx_Version::VERSION,
        ]));
    }

    private function renderTemplateFile($path, array $vars = [])
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template "%s" was not found.', $path));
        }

        extract($vars, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    private function resolveTemplatePath($name)
    {
        $relativePath = preg_replace('#^[.][\\\\/]#', '', $name);
        $relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);

        return PS_PATH_INSTALL_THEMES . DIRECTORY_SEPARATOR . $relativePath;
    }

    private function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function getLicense()
    {
        $path = PS_PATH_LICENSE;
        if (!file_exists($path)) {
            return 'OHMS is licensed under the Apache License, Version 2.0.'.PHP_EOL.'Please visit https://github.com/OHMS/OHMS/blob/master/LICENSE for full license text.';
        }
        return file_get_contents($path);
    }

    private function getPdo($host, $port, $db, $user, $pass)
    {
        $pdo = new \PDO('mysql:host='.$host.';port='.$port,
            $user,
            $pass,
            array(
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY         => true,
                \PDO::ATTR_ERRMODE                          => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE               => \PDO::FETCH_ASSOC,
            )
        );

        $pdo->exec( 'SET NAMES "utf8"' );
        $pdo->exec( 'SET CHARACTER SET utf8' );
        $pdo->exec( 'SET CHARACTER_SET_CONNECTION = utf8' );
        $pdo->exec( 'SET character_set_results = utf8' );
        $pdo->exec( 'SET character_set_server = utf8' );
        $pdo->exec( 'SET SESSION interactive_timeout = 28800' );
        $pdo->exec( 'SET SESSION wait_timeout = 28800' );

        // try create database if permissions allows
        try {
            $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8 COLLATE utf8_general_ci;");
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }

        $pdo->query("USE $db;");
        return $pdo;
    }

    private function canConnectToDatabase($host, $port, $db, $user, $pass)
    {
        try {
            $this->getPdo($host, $port, $db, $user, $pass);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }

    private function isValidAdmin($email, $pass, $name)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (empty($pass)) {
            return false;
        }

        if (empty($name)) {
            return false;
        }

        return true;
    }

    private function makeInstall($ns)
    {
        $this->_isValidInstallData($ns);
        $this->_createConfigurationFile($ns);

        $pdo = $this->getPdo($ns->get('db_host'), $ns->get('db_port'), $ns->get('db_name'), $ns->get('db_user'), $ns->get('db_pass'));

        $sql = file_get_contents(PS_PATH_SQL);
        $sql_content = file_get_contents(PS_PATH_SQL_DATA);

        if (!$sql || !$sql_content) {
            throw new Exception('Could not read structure.sql file');
        }

        $sql .= $sql_content;

        $sql = preg_split('/\;[\r]*\n/ism', $sql);
        $sql = array_map('trim', $sql);
        $err = '';
        foreach ($sql as $query) {
            if (!trim($query)) {
                continue;
            }

            $res = $pdo->query($query);
        }

        $passwordObject = new \Priyx_Password();
        $stmt = $pdo->prepare("INSERT INTO admin (role, name, email, pass, protected, created_at, updated_at) VALUES('admin', :admin_name, :admin_email, :admin_pass, 1, NOW(), NOW());");
        $stmt->execute(array(
            'admin_name'  => $ns->get('admin_name'),
            'admin_email' => $ns->get('admin_email'),
            'admin_pass'  => $passwordObject->hashIt($ns->get('admin_pass')),
        ));

        try {
            $this->_sendMail($ns);
        } catch (Exception $e) {
            // E-mail was not sent, but that is not a problem
            error_log($e->getMessage());
        }
        
        
        /*
          Copy config templates when applicable
        */
        if(!file_exists(PS_PATH_HTACCESS) && file_exists(PS_PATH_HTACCESS_TEMPLATE)) {
            rename(PS_PATH_HTACCESS_TEMPLATE, PS_PATH_HTACCESS);
        }

        if(!file_exists(PS_BS_CONFIG) && file_exists(PS_BS_CONFIG_TEMPLATE)) {
            rename(PS_BS_CONFIG_TEMPLATE, PS_BS_CONFIG);
        }

        if(!file_exists(PS_HURAGA_CONFIG) && file_exists(PS_HURAGA_CONFIG_TEMPLATE)) {
            rename(PS_HURAGA_CONFIG_TEMPLATE, PS_HURAGA_CONFIG);
        }

        if(!file_exists(PS_BBTHEME_CONFIG) && file_exists(PS_BBTHEM_CONFIG_TEMPLATE)) {
            rename(PS_BBTHEM_CONFIG_TEMPLATE, PS_BBTHEME_CONFIG);
        }
        return true;
    }

    private function _sendMail($ns)
    {
        $admin_name = $ns->get('admin_name');
        $admin_email = $ns->get('admin_email');
        $admin_pass = $ns->get('admin_pass');

        $content = "Hi $admin_name, " . PHP_EOL;
        $content .= "You have successfully setup OHMS at " . PS_URL . PHP_EOL;
        $content .= "Access client area at: " . PS_URL . PHP_EOL;
        $content .= "Access admin area at: " . PS_URL_ADMIN . " with login details:" . PHP_EOL;
        $content .= "E-mail: " . $admin_email . PHP_EOL;
        $content .= "Password: " . $admin_pass . PHP_EOL . PHP_EOL;

        $content .= "Read OHMS documentation to get started https://docs.OHMS.org/" . PHP_EOL;
        $content .= "Thank You for using OHMS." . PHP_EOL;

        $subject = sprintf('OHMS is ready at "%s"', PS_URL);

        @mail($admin_email, $subject, $content);
    }

    private function _createConfigurationFile($data)
    {
        $output = $this->_getConfigOutput($data);
        $this->prepareWritableFile(PS_PATH_CONFIG);

        if (@file_put_contents(PS_PATH_CONFIG, $output) === false) {
            throw new Exception('Configuration file is not writable or does not exist. Please create the file at ' . PS_PATH_CONFIG . ' and make it writable', 101);
        }
    }

    private function prepareWritableFile($path)
    {
        clearstatcache(true, $path);

        if (is_file($path) && is_writable($path)) {
            return true;
        }

        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            return false;
        }

        if (!file_exists($path) && @file_put_contents($path, '') === false) {
            return false;
        }

        @chmod($path, 0666);
        clearstatcache(true, $path);

        if (is_file($path) && is_writable($path)) {
            return true;
        }

        if (is_file($path) && @filesize($path) === 0) {
            @unlink($path);
            @file_put_contents($path, '');
            @chmod($path, 0666);
            clearstatcache(true, $path);
        }

        return is_file($path) && is_writable($path);
    }

    private function _getConfigOutput($ns)
    {
        // TODO: Why not just take the defaults from the bb.config.example.php file and modify accordingly? Also this method doesn't preserve the comments in the example config.
        $data = [
            'debug' => false,

            'maintenance_mode' => [
                'enabled' => false,
                'allowed_urls' => [],
                'allowed_ips' => [],
            ],
            
            'salt' => md5(random_bytes(13)),
            'url' => PS_URL,
            'admin_area_prefix' => '/ohms-admin',
            'sef_urls' => true,
            'timezone' => 'UTC',
            'locale' => 'en_US',
            'locale_date_format' => '%A, %d %B %G',
            'locale_time_format' => ' %T',
            'path_data' => PS_PATH_ROOT . '/data',
            'path_logs' => PS_PATH_ROOT . '/data/log/application.log',

            'log_to_db' => true,

            'db' => [
                'type' => 'mysql',
                'host' => $ns->get('db_host'),
                'port' => $ns->get('db_port'),
                'name' => $ns->get('db_name'),
                'user' => $ns->get('db_user'),
                'password' => $ns->get('db_pass'),
            ],

            'twig' => [
                'debug' => true,
                'auto_reload' => true,
                'cache' => PS_PATH_ROOT . '/data/cache',
            ],

            'api' => [
                'require_referrer_header' => false,
                'allowed_ips' => [],
                'rate_span' => 60 * 60,
                'rate_limit' => 1000,
                'throttle_delay' => 2,
                'rate_span_login' =>  60,
                'rate_limit_login' => 20,
            ],
            'guzzle' => [
                'user_agent' => 'Mozilla/5.0 (RedHatEnterpriseLinux; Linux x86_64; OHMS; +http://OHMS.org) Gecko/20100101 Firefox/93.0',
                'timeout' => 0,
                'upgrade_insecure_requests' => 0,
            ],
        ];
        $output = '<?php ' . PHP_EOL;
        $output .= 'return ' . var_export($data, true) . ';';
        return $output;
    }

    private function _isValidInstallData($ns)
    {
        if (!$this->canConnectToDatabase($ns->get('db_host'), $ns->get('db_port'), $ns->get('db_name'), $ns->get('db_user'), $ns->get('db_pass'))) {
            throw new Exception('Can not connect to database');
        }

        if (!$this->isValidAdmin($ns->get('admin_email'), $ns->get('admin_pass'), $ns->get('admin_name'))) {
            throw new Exception('Administrators account is not valid');
        }
    }

    private function generateEmailTemplates()
    {
        define('PS_PATH_MODS', PS_PATH_ROOT . '/modules');

        $emailService = new \Priyx\Mod\Email\Service();
        $di = $di = include PS_PATH_ROOT . '/di.php';
        $di['translate']();
        $emailService->setDi($di);
        return $emailService->templateBatchGenerate();
    }
}

$action = isset($_GET['a']) ? $_GET['a'] : 'index';
$installer = new Priyx_Installer;
$installer->run($action);
