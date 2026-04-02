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


defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));
define('PS_PATH_ROOT', dirname(__FILE__));
define('PS_PATH_VENDOR', PS_PATH_ROOT.'/ps-vendor');
define('PS_PATH_LIBRARY', PS_PATH_ROOT.'/library');
define('PS_PATH_THEMES', PS_PATH_ROOT.'/themes');
define('PS_PATH_ADMIN_THEMES', PS_PATH_ROOT.'/admin/template');
define('PS_PATH_MODS', PS_PATH_ROOT.'/modules');
define('PS_PATH_LANGS', PS_PATH_ROOT.'/locale');
define('PS_PATH_UPLOADS', PS_PATH_ROOT.'/uploads');
define('PS_PATH_DATA', PS_PATH_ROOT.'/data');
define('isCLI', 'cli' == php_sapi_name());

function handler_error(int $number, string $message, string $file, int $line)
{
    if (E_RECOVERABLE_ERROR === $number) {
        if (isCLI) {
            echo "Error #[$number] occurred in [$file] at line [$line]: [$message]";
        } else {
            handler_exception(new ErrorException($message, $number, 0, $file, $line));
        }
    } else {
        error_log($number.' '.$message.' '.$file.' '.$line);
    }

    return false;
}

// Removed Exception type. Some errors are thrown as exceptions causing fatal errors.
function handler_exception($e)
{
    if (isCLI) {
        echo 'Error #['.$e->getCode().'] occurred in ['.$e->getFile().'] at line ['.$e->getLine().']: ['.trim(strip_tags($e->getMessage())).']';
    } else {
        if (APPLICATION_ENV == 'testing') {
            echo $e->getMessage().PHP_EOL;

            return;
        }
        error_log($e->getMessage());

        if (defined('PS_MODE_API')) {
            $code = $e->getCode() ? $e->getCode() : 9998;
            $result = ['result' => null, 'error' => ['message' => $e->getMessage(), 'code' => $code]];
            echo json_encode($result);

            return false;
        }

        $page = "<!DOCTYPE html>
      <html lang=\"en\">
      <head>
          <meta charset=\"utf-8\">
          <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
          <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
          <title>An error occurred</title>
          <link href=\"https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap\" rel=\"stylesheet\">
          <style>
            :root {
                --primary: #388dbc;
                --text-main: #151723;
                --text-muted: #64748b;
                --bg: #f8fafc;
            }
            * { box-sizing: border-box; }
            body {
                background: var(--bg);
                font-family: 'Plus Jakarta Sans', sans-serif;
                margin: 0;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                color: var(--text-main);
            }
            .content {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            .error-card {
                background: white;
                max-width: 500px;
                width: 100%;
                padding: 3rem 2rem;
                border-radius: 24px;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
                text-align: center;
            }
            .logo-container {
                margin-bottom: 2rem;
            }
            .logo-placeholder {
                width: 80px;
                height: 80px;
                background: var(--primary);
                display: inline-block;
                border-radius: 20px;
                background-image: url('https://i.pinimg.com/736x/93/5a/70/935a704648fc2c4c87bfba552ba24759.jpg');
                background-size: cover;
                background-position: center;
            }
            h1 {
                font-size: 2rem;
                font-weight: 700;
                margin: 0 0 0.5rem;
                color: var(--text-main);
            }
            h2 {
                font-size: 1rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                color: var(--primary);
                margin-bottom: 1.5rem;
            }
            p {
                color: var(--text-muted);
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            .actions {
                border-top: 1px solid #f1f5f9;
                padding-top: 2rem;
                margin-top: 2rem;
            }
            .btn-link {
                color: var(--primary);
                text-decoration: none;
                font-weight: 600;
                font-size: 0.9375rem;
                transition: opacity 0.2s;
            }
            .btn-link:hover { opacity: 0.8; }
            footer {
                padding: 2rem;
                text-align: center;
                font-size: 0.875rem;
                color: var(--text-muted);
            }
            footer a {
                color: var(--primary);
                text-decoration: none;
                font-weight: 600;
            }
          </style>
      </head>
      <body>
          <div class=\"content\">
              <div class=\"error-card\">
                  <div class=\"logo-container\">
                      <div class=\"logo-placeholder\"></div>
                  </div>
                  <h1>An Error Occurred</h1>";

            $page = str_replace(PHP_EOL, '', $page);
            echo $page;

            if ($e->getCode()) {
                echo sprintf('<h2>Error Info: %s</h2>', $e->getCode());
            } else {
                 echo '<h2>System Exception</h2>';
            }

            echo sprintf('<p>%s</p>', $e->getMessage());

            echo '<div class="actions">';
            echo sprintf('<a href="https://priyxstudio.in/getohms" class="btn-link" target="_blank">Search for solutions &rarr;</a>', urlencode($e->getMessage()));
            echo '</div>';

            echo '</div>
          </div>
          <footer>
              Powered by <a href="https://priyxstudio.in/getohms" target="_blank">OHMS</a>
          </footer>
      </body>
      </html>';
    }
}

set_exception_handler('handler_exception');
set_error_handler('handler_error');

// Check for Composer packages
if (!file_exists(PS_PATH_VENDOR)) {
    throw new Exception("It seems like Composer packages are missing. You have to run \"<code>composer install</code>\" in order to install them. For detailed instruction, you can see <a href=\"https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies\">Composer's getting started guide</a>.<br /><br />If you have downloaded OHMS from <a href=\"https://github.com/OHMS/OHMS/releases\">GitHub releases</a>, this shouldn't happen.", 110);
}

// Multisite support. Load new configuration depending on the current hostname
// If being run from CLI, first parameter must be the hostname
$configPath = PS_PATH_ROOT.'/config.php';
if ((isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) || ('cli' == php_sapi_name() && isset($argv[1]))) {
    if ('cli' == php_sapi_name()) {
        $host = $argv[1];
    } else {
        $host = $_SERVER['HTTP_HOST'];
    }

    $predictConfigPath = PS_PATH_ROOT.'/config-'.$host.'.php';
    if (file_exists($predictConfigPath)) {
        $configPath = $predictConfigPath;
    }
}

// Try to check if configuration is available
if (!file_exists($configPath) || 0 == filesize($configPath)) {
    // Try to create an empty configuration file
    @file_put_contents($configPath, '');

    $base_url = 'http'.(isset($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'] || 1 == $_SERVER['HTTPS']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO'] ? 's' : '').'://'.$_SERVER['HTTP_HOST'];
    $base_url .= preg_replace('@/+$@', '', dirname($_SERVER['SCRIPT_NAME']));
    $url = $base_url.'/install/index.php';

    if (file_exists(PS_PATH_ROOT.'/install/index.php')) {
        header("Location: $url");
    }

    $configFile = pathinfo($configPath, PATHINFO_BASENAME);
    $msg = sprintf("Your <b><em>$configFile</em></b> file seems to be invalid. It's possible that your preexisting configuration file may not contain the required configuration parameters or have become corrupted. OHMS needs to have a valid configuration file present in order to function properly.</p> <p>Please use the example config as reference <a target='_blank' href='https://raw.githubusercontent.com/OHMS/OHMS/master/src/config-sample.php'>here</a>. You may need to manually restore a old config file or fix your existing one.</p>");
    throw new Exception($msg, 101);
}

$config = require_once $configPath;
if (!is_array($config)) {
    $configFile = pathinfo($configPath, PATHINFO_BASENAME);
    $msg = sprintf("Your <b><em>$configFile</em></b> file seems to be invalid. It's possible that your preexisting configuration file may not contain the required configuration parameters or have become corrupted. OHMS needs to have a valid configuration file present in order to function properly.</p> <p>Please use the example config as reference <a target='_blank' href='https://raw.githubusercontent.com/OHMS/OHMS/master/src/config-sample.php'>here</a>. You may need to manually restore a old config file or fix your existing one.</p>");
    throw new Exception($msg, 101);
}

require PS_PATH_VENDOR.'/autoload.php';

$installExists = file_exists(PS_PATH_ROOT.'/install/index.php');
$hasStarterConfig = is_array($config)
    && empty($config['salt'])
    && isset($config['db'])
    && ($config['db']['user'] ?? null) === 'foo'
    && ($config['db']['password'] ?? null) === 'foo';

if (!isCLI && $installExists && $hasStarterConfig && !headers_sent()) {
    $base_url = 'http'.(isset($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'] || 1 == $_SERVER['HTTPS']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO'] ? 's' : '').'://'.$_SERVER['HTTP_HOST'];
    $base_url .= preg_replace('@/+$@', '', dirname($_SERVER['SCRIPT_NAME']));
    header('Location: '.$base_url.'/install/index.php');
    exit;
}

// Try to check if /install directory still exists, even after the installation was completed
if ($config['debug'] == false && file_exists($configPath) && 0 !== filesize($configPath) && $installExists) {
    throw new Exception('For safety reasons, you have to delete the <b><em>/install</em></b> directory to start using OHMS.</p><p>Please delete the <b><em>/install</em></b> directory from your web server.', 102);
}

date_default_timezone_set($config['timezone']);

define('PS_DEBUG', $config['debug']);
define('PS_URL', $config['url']);
define('PS_SEF_URLS', $config['sef_urls']);
define('PS_PATH_CACHE', $config['path_data'].'/cache');
define('PS_PATH_LOG', $config['path_data'].'/log');
define('PS_SSL', ('https' === substr($config['url'], 0, 5)));

if (!isCLI && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

if ($config['sef_urls']) {
    define('PS_URL_API', $config['url'].'api/');
} else {
    define('PS_URL_API', $config['url'].'index.php?_url=/api/');
}

if ($config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_RECOVERABLE_ERROR);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

ini_set('log_errors', '1');
ini_set('html_errors', false);
ini_set('error_log', PS_PATH_LOG.'/php_error.log');
