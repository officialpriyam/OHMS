<?php
return array (
  'debug' => false,
  'maintenance_mode' => 
  array (
    'enabled' => false,
    'allowed_urls' => 
    array (
    ),
    'allowed_ips' => 
    array (
    ),
  ),
  'salt' => 'bf9dbabe30934b72cca643832444bb92',
  'url' => 'http://localhost:8000/',
  'admin_area_prefix' => '/ohms-admin',
  'sef_urls' => true,
  'timezone' => 'UTC',
  'locale' => 'en_US',
  'locale_date_format' => '%A, %d %B %G',
  'locale_time_format' => ' %T',
  'path_data' => 'C:\\Users\\User\\Desktop\\OHMS/data',
  'path_logs' => 'C:\\Users\\User\\Desktop\\OHMS/data/log/application.log',
  'log_to_db' => true,
  'db' => 
  array (
    'type' => 'mysql',
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'my_new_db',
    'user' => 'user',
    'password' => 'your_strong_password',
  ),
  'twig' => 
  array (
    'debug' => true,
    'auto_reload' => true,
    'cache' => 'C:\\Users\\User\\Desktop\\OHMS/data/cache',
  ),
  'api' => 
  array (
    'require_referrer_header' => false,
    'allowed_ips' => 
    array (
    ),
    'rate_span' => 3600,
    'rate_limit' => 1000,
    'throttle_delay' => 2,
    'rate_span_login' => 60,
    'rate_limit_login' => 20,
  ),
  'guzzle' => 
  array (
    'user_agent' => 'Mozilla/5.0 (RedHatEnterpriseLinux; Linux x86_64; OHMS; +http://OHMS.org) Gecko/20100101 Firefox/93.0',
    'timeout' => 0,
    'upgrade_insecure_requests' => 0,
  ),
  'license' => 
  array (
    'key' => 'CE29AA49-3E84-0BDD-265F-DC074BF9ECFD',
    'product' => 'ohms-beta',
    'device_id' => '0247845c98bc618dca8e4faadf5005a1',
    'status' => 'active',
    'last_check' => 1774930557,
  ),
);
