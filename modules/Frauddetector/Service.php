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



namespace Priyx\Mod\Frauddetector;

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
        $this->ensureSchema();

        return true;
    }

    public function update($manifest = null)
    {
        $this->ensureSchema();

        return true;
    }

    public static function onBeforeClientSignUp(\Priyx_Event $event)
    {
        $event->getDi()['mod_service']('Frauddetector')->guardSignup($event->getParameters());
    }

    public static function onBeforeGuestPublicTicketOpen(\Priyx_Event $event)
    {
        $event->getDi()['mod_service']('Frauddetector')->guardPublicTicket($event->getParameters());
    }

    public static function onBeforeClientCheckout(\Priyx_Event $event)
    {
        $event->getDi()['mod_service']('Frauddetector')->guardCheckout($event->getParameters());
    }

    public function guardSignup(array $params): void
    {
        $this->evaluateOrFail('signup', $params);
    }

    public function guardPublicTicket(array $params): void
    {
        $this->evaluateOrFail('public_ticket', $params);
    }

    public function guardCheckout(array $params): void
    {
        $this->evaluateOrFail('checkout', $params);
    }

    public function getLogList(array $data = []): array
    {
        $this->ensureSchema();

        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($data['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $days = isset($data['days']) && $data['days'] !== '' ? max(1, (int) $data['days']) : null;

        $where = [];
        $bindings = [];
        if (!empty($data['event_type'])) {
            $where[] = 'event_type = :event_type';
            $bindings[':event_type'] = $data['event_type'];
        }
        if (!empty($data['decision'])) {
            $where[] = 'decision = :decision';
            $bindings[':decision'] = $data['decision'];
        }
        if (!empty($data['ip'])) {
            $where[] = 'ip = :ip';
            $bindings[':ip'] = trim((string) $data['ip']);
        }
        if ($days !== null) {
            $where[] = 'created_at >= :days_threshold';
            $bindings[':days_threshold'] = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $total = (int) $this->di['db']->getCell('SELECT COUNT(*) FROM mod_frauddetector_log' . $whereSql, $bindings);
        $rows = $this->di['db']->getAll(
            'SELECT * FROM mod_frauddetector_log' . $whereSql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $bindings
        );

        foreach ($rows as &$row) {
            $row = $this->toLogApiArray($row);
        }

        return [
            'list' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        ];
    }

    public function deleteLog(int $id): bool
    {
        $this->ensureSchema();
        $this->di['db']->exec('DELETE FROM mod_frauddetector_log WHERE id = :id', [':id' => $id]);

        return true;
    }

    public function clearLogs(?int $days = null): bool
    {
        $this->ensureSchema();

        if ($days !== null && $days > 0) {
            $this->di['db']->exec(
                'DELETE FROM mod_frauddetector_log WHERE created_at < :threshold',
                [':threshold' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
            );

            return true;
        }

        $this->di['db']->exec('DELETE FROM mod_frauddetector_log');

        return true;
    }

    public function toLogApiArray(array $row): array
    {
        $details = [];
        if (!empty($row['details'])) {
            $decoded = json_decode((string) $row['details'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        $row['details'] = $details;

        return $row;
    }

    protected function ensureSchema(): void
    {
        $this->di['db']->exec(
            "CREATE TABLE IF NOT EXISTS `mod_frauddetector_log` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `event_type` varchar(64) NOT NULL,
                `ip` varchar(64) DEFAULT NULL,
                `email` varchar(255) DEFAULT NULL,
                `client_id` int(11) DEFAULT NULL,
                `score` int(11) NOT NULL DEFAULT 0,
                `decision` varchar(32) NOT NULL,
                `details` text DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_mod_frauddetector_event_type` (`event_type`),
                KEY `idx_mod_frauddetector_ip` (`ip`),
                KEY `idx_mod_frauddetector_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
    }

    protected function evaluateOrFail(string $eventType, array $params): void
    {
        $config = $this->getConfig();
        if (empty($config['enabled'])) {
            return;
        }

        $result = $this->evaluateRisk($eventType, $params, $config);
        $this->logEvaluation($eventType, $result);

        if ($result['blocked']) {
            throw new \Priyx_Exception($this->buildMessage($config, $result), null, 403);
        }
    }

    protected function evaluateRisk(string $eventType, array $params, array $config): array
    {
        $ip = trim((string) ($params['ip'] ?? $this->di['request']->getClientAddress()));
        $clientId = isset($params['client_id']) ? (int) $params['client_id'] : null;
        $email = $this->resolveEmail($params, $clientId);
        $domain = $this->getEmailDomain($email);
        $score = 0;
        $reasons = [];

        if ($domain !== '' && $this->domainMatches($domain, $this->normalizeLines((string) $config['blocked_email_domains']))) {
            $score += 100;
            $reasons[] = 'Blocked email domain';
        }

        if ($domain !== '' && $this->domainMatches($domain, $this->normalizeLines((string) $config['disposable_domains']))) {
            $score += 60;
            $reasons[] = 'Disposable email domain';
        }

        if ($eventType === 'signup') {
            $score += $this->applyRateLimitScore($reasons, 'Too many signups from this IP', 'signup', $ip, (int) $config['max_signups_per_ip'], (int) $config['signup_window_minutes'], 55);
        }

        if ($eventType === 'public_ticket') {
            $score += $this->applyRateLimitScore($reasons, 'Too many public tickets from this IP', 'public_ticket', $ip, (int) $config['max_public_tickets_per_ip'], (int) $config['public_ticket_window_minutes'], 45);
        }

        if ($eventType === 'checkout') {
            $score += $this->applyRateLimitScore($reasons, 'Too many checkout attempts from this IP', 'checkout', $ip, (int) $config['max_orders_per_ip'], (int) $config['order_window_minutes'], 65);

            $newClientAgeHours = (int) $config['new_client_age_hours'];
            $maxOrdersForNewClient = (int) $config['max_total_new_client'];
            if ($clientId && $newClientAgeHours > 0 && $maxOrdersForNewClient > 0 && $this->isClientNew($clientId, $newClientAgeHours) && $this->countClientOrders($clientId) >= $maxOrdersForNewClient) {
                $score += 40;
                $reasons[] = 'New client exceeded allowed order volume';
            }
        }

        $threshold = max(1, (int) $config['risk_threshold']);

        return [
            'event_type' => $eventType,
            'ip' => $ip,
            'email' => $email,
            'client_id' => $clientId,
            'score' => $score,
            'threshold' => $threshold,
            'blocked' => $score >= $threshold,
            'decision' => $score >= $threshold ? 'blocked' : 'allowed',
            'reasons' => $reasons,
        ];
    }

    protected function logEvaluation(string $eventType, array $result): void
    {
        $this->ensureSchema();

        $details = [
            'event_type' => $eventType,
            'threshold' => $result['threshold'],
            'blocked' => $result['blocked'],
            'reasons' => $result['reasons'],
        ];

        $this->di['db']->exec(
            'INSERT INTO mod_frauddetector_log (event_type, ip, email, client_id, score, decision, details, created_at, updated_at)
             VALUES (:event_type, :ip, :email, :client_id, :score, :decision, :details, :created_at, :updated_at)',
            [
                ':event_type' => $eventType,
                ':ip' => $result['ip'] ?: null,
                ':email' => $result['email'] ?: null,
                ':client_id' => $result['client_id'] ?: null,
                ':score' => $result['score'],
                ':decision' => $result['decision'],
                ':details' => json_encode($details),
                ':created_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    protected function buildMessage(array $config, array $result): string
    {
        $message = trim((string) ($config['message'] ?? ''));
        if ($message === '') {
            $message = 'This request was blocked by fraud screening. Please contact support if you need help.';
        }

        return strtr($message, [
            '{{event}}' => $result['event_type'],
            '{{ip}}' => (string) $result['ip'],
            '{{score}}' => (string) $result['score'],
        ]);
    }

    protected function getConfig(): array
    {
        return array_merge(
            [
                'enabled' => 0,
                'risk_threshold' => 80,
                'blocked_email_domains' => '',
                'disposable_domains' => '',
                'max_signups_per_ip' => 3,
                'signup_window_minutes' => 60,
                'max_orders_per_ip' => 4,
                'order_window_minutes' => 120,
                'max_public_tickets_per_ip' => 5,
                'public_ticket_window_minutes' => 120,
                'max_total_new_client' => 2,
                'new_client_age_hours' => 24,
                'message' => 'This request was blocked by fraud screening. Please contact support if you need help.',
            ],
            (array) $this->di['mod_config']('Frauddetector')
        );
    }

    protected function resolveEmail(array $params, ?int $clientId): string
    {
        $email = strtolower(trim((string) ($params['email'] ?? '')));
        if ($email !== '') {
            return $email;
        }

        if ($clientId) {
            $client = $this->di['db']->load('Client', $clientId);
            if ($client instanceof \Model_Client) {
                return strtolower(trim((string) $client->email));
            }
        }

        return '';
    }

    protected function getEmailDomain(string $email): string
    {
        $parts = explode('@', $email);

        return count($parts) === 2 ? strtolower(trim($parts[1])) : '';
    }

    protected function domainMatches(string $domain, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }

            if ($domain === $pattern) {
                return true;
            }

            if (strpos($pattern, '*') !== false) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
                if (preg_match($regex, $domain)) {
                    return true;
                }
            }

            if (substr($pattern, 0, 1) === '.' && str_ends_with($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeLines(string $value): array
    {
        $items = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $items = array_map('trim', $items);

        return array_values(array_filter($items, static function ($item) {
            return $item !== '' && strpos($item, '#') !== 0;
        }));
    }

    protected function applyRateLimitScore(array &$reasons, string $reason, string $eventType, string $ip, int $limit, int $windowMinutes, int $score): int
    {
        if ($ip === '' || $limit <= 0 || $windowMinutes <= 0) {
            return 0;
        }

        if ($this->countRecentLogEvents($eventType, $ip, $windowMinutes) >= $limit) {
            $reasons[] = $reason;

            return $score;
        }

        return 0;
    }

    protected function countRecentLogEvents(string $eventType, string $ip, int $windowMinutes): int
    {
        $this->ensureSchema();

        return (int) $this->di['db']->getCell(
            'SELECT COUNT(*) FROM mod_frauddetector_log
             WHERE event_type = :event_type AND ip = :ip AND created_at >= :threshold',
            [
                ':event_type' => $eventType,
                ':ip' => $ip,
                ':threshold' => date('Y-m-d H:i:s', strtotime('-' . $windowMinutes . ' minutes')),
            ]
        );
    }

    protected function isClientNew(int $clientId, int $ageHours): bool
    {
        $client = $this->di['db']->load('Client', $clientId);
        if (!$client instanceof \Model_Client || empty($client->created_at)) {
            return false;
        }

        return strtotime((string) $client->created_at) >= strtotime('-' . $ageHours . ' hours');
    }

    protected function countClientOrders(int $clientId): int
    {
        return (int) $this->di['db']->getCell(
            'SELECT COUNT(*) FROM client_order WHERE client_id = :client_id',
            [':client_id' => $clientId]
        );
    }
}
