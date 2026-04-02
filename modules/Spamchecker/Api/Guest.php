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



/**
 * Spam cheking module management.
 */

namespace Priyx\Mod\Spamchecker\Api;

class Guest extends \Api_Abstract
{
    /**
     * Returns recaptcha public key.
     *
     * @return string
     */
    public function recaptcha($data)
    {
        $config = $this->di['mod_config']('Spamchecker');
        $result = [
            'publickey' => $this->di['array_get']($config, 'captcha_recaptcha_publickey', null),
            'enabled' => $this->di['array_get']($config, 'captcha_enabled', false),
            'version' => $this->di['array_get']($config, 'captcha_version', null),
        ];

        return $result;
    }
}
