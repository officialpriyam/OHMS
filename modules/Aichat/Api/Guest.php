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

namespace Priyx\Mod\Aichat\Api;

class Guest extends \Api_Abstract
{
    public function chat($data)
    {
        $prompt = $data['prompt'] ?? '';
        $history = $data['history'] ?? [];
        
        if (empty($prompt)) {
            throw new \Exception('Prompt is required');
        }
        
        return $this->di['mod_service']('aichat')->ask($prompt, $history);
    }
}
