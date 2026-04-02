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




class Priyx_Authorization
{
    private $di = null;
    private $session = null;

    public function __construct(Priyx_Di $di)
    {
        $this->di = $di;
        $this->session = $di['session'];
    }

    public function isClientLoggedIn()
    {
        return (bool)($this->session->get('client_id'));
    }

    public function isAdminLoggedIn()
    {
        return (bool)($this->session->get('admin'));
    }

    public function authorizeUser($user, $plainTextPassword)
    {
        $user = $this->passwordBackwardCompatibility($user, $plainTextPassword);
        if ($this->di['password']->verify($plainTextPassword, $user->pass)){
            if ($this->di['password']->needsRehash($user->pass)){
                $user->pass = $this->di['password']->hashIt($plainTextPassword);
                $this->di['db']->store($user);
            }
            return $user;
        }
        return null;
    }

    public function passwordBackwardCompatibility($user, $plainTextPassword)
    {
        if (!$this->isLegacySha1FallbackEnabled()) {
            return $user;
        }

        if ($this->isLegacySha1Hash($user->pass) && hash_equals($user->pass, sha1((string) $plainTextPassword))) {
            $user->pass = $this->di['password']->hashIt($plainTextPassword);
            $this->di['db']->store($user);
        }
        return $user;
    }

    private function isLegacySha1FallbackEnabled()
    {
        $config = $this->di['config'];

        return empty($config['disable_legacy_sha1_passwords']);
    }

    private function isLegacySha1Hash($hash)
    {
        return is_string($hash) && preg_match('/^[a-f0-9]{40}$/i', $hash);
    }

}
