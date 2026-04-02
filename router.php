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



$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the file exists on disk, serve it directly (CSS, JS, images, etc.)
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false; // Let the built-in server handle it
}

// Let the built-in server serve directory index files like /install/index.php.
if ($uri !== '/' && is_dir(__DIR__ . $uri)) {
    return false;
}

// Route everything else through index.php
$_GET['_url'] = $uri;
require __DIR__ . '/index.php';
