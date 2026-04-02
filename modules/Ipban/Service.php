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



namespace Priyx\Mod\Ipban;

use Priyx\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function install()
    {
        return true;
    }

    public function update($manifest = null)
    {
        return true;
    }

    public static function onBeforeClientLogin(\Priyx_Event $event)
    {
        $event->getDi()['mod_service']('Ipban')->assertIpAllowed($event);
    }

    public static function onBeforeAdminLogin(\Priyx_Event $event)
    {
        $event->getDi()['mod_service']('Ipban')->assertIpAllowed($event);
    }

    public static function onBeforeClientSignUp(\Priyx_Event $event)
    {
        $event->getDi()['mod_service']('Ipban')->assertIpAllowed($event);
    }

    public static function onBeforeGuestPublicTicketOpen(\Priyx_Event $event)
    {
        $event->getDi()['mod_service']('Ipban')->assertIpAllowed($event);
    }

    public static function onBeforeClientCheckout(\Priyx_Event $event)
    {
        $event->getDi()['mod_service']('Ipban')->assertIpAllowed($event);
    }

    public function assertIpAllowed(\Priyx_Event $event): void
    {
        $config = $this->getConfig();
        if (empty($config['enabled'])) {
            return;
        }

        $params = $event->getParameters();
        $ip = trim((string) ($params['ip'] ?? $this->di['request']->getClientAddress()));
        if ($ip === '') {
            return;
        }

        $patterns = array_merge(
            $this->normalizeLines((string) ($config['blocked_ips'] ?? '')),
            $this->normalizeLines((string) ($config['blocked_ranges'] ?? ''))
        );

        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($ip, $pattern)) {
                throw new \Priyx_Exception($this->buildMessage((string) ($config['message'] ?? ''), $ip), null, 403);
            }
        }
    }

    protected function getConfig(): array
    {
        return array_merge(
            [
                'enabled' => 0,
                'blocked_ips' => '',
                'blocked_ranges' => '',
                'message' => 'Access from {{ip}} has been blocked. Please contact support if you believe this is an error.',
            ],
            (array) $this->di['mod_config']('Ipban')
        );
    }

    protected function normalizeLines(string $value): array
    {
        $items = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $items = array_map('trim', $items);

        return array_values(array_filter($items, static function ($item) {
            return $item !== '' && strpos($item, '#') !== 0;
        }));
    }

    protected function matchesPattern(string $ip, string $pattern): bool
    {
        if ($pattern === $ip) {
            return true;
        }

        if (strpos($pattern, '*') !== false) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
            if (preg_match($regex, $ip)) {
                return true;
            }
        }

        if (strpos($pattern, '/') !== false) {
            return $this->matchesCidr($ip, $pattern);
        }

        return false;
    }

    protected function matchesCidr(string $ip, string $pattern): bool
    {
        [$network, $prefix] = array_pad(explode('/', $pattern, 2), 2, null);
        if ($network === null || $prefix === null) {
            return false;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $prefix = (int) $prefix;
        if ($prefix < 0 || $prefix > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        if ($ipLong === false || $networkLong === false) {
            return false;
        }

        $mask = $prefix === 0 ? 0 : (-1 << (32 - $prefix));

        return (($ipLong & $mask) === ($networkLong & $mask));
    }

    protected function buildMessage(string $message, string $ip): string
    {
        $message = trim($message);
        if ($message === '') {
            $message = 'Access from {{ip}} has been blocked. Please contact support if you believe this is an error.';
        }

        return strtr($message, [
            '{{ip}}' => $ip,
            ':ip' => $ip,
        ]);
    }
}
