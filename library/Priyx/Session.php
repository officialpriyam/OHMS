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




class Priyx_Session
{
    public function __construct($handler)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_save_handler($handler, true);
        if (php_sapi_name() !== 'cli') {
            session_set_cookie_params($this->getCookieParams());
            session_start();
        }
    }

    public function getId()
    {
        return session_id();
    }

    public function delete($key)
    {
        unset($_SESSION[$key]);
    }

    public function get($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }
    
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function regenerateId($deleteOldSession = true)
    {
        if (php_sapi_name() === 'cli') {
            return true;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return session_regenerate_id((bool) $deleteOldSession);
    }

    public function destroy()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        return session_destroy();
    }

    private function getCookieParams(): array
    {
        $currentCookieParams = session_get_cookie_params();

        return [
            'lifetime' => (int) ($currentCookieParams['lifetime'] ?? 0),
            'path' => $currentCookieParams['path'] ?? '/',
            'domain' => $currentCookieParams['domain'] ?? '',
            'secure' => $this->isSecureConnection(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private function isSecureConnection(): bool
    {
        if (defined('PS_SSL') && PS_SSL) {
            return true;
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ('on' === $https || '1' === $https) {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if (str_contains($forwardedProto, 'https')) {
            return true;
        }

        $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ('on' === $forwardedSsl) {
            return true;
        }

        return '443' === (string) ($_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? '');
    }
}
