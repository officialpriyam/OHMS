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

namespace Priyx\Mod\Servicepterodactyl\Api;

class Admin extends \Api_Abstract
{
    public function get_nests($data)
    {
        return $this->getService()->getNests();
    }

    public function get_eggs($data)
    {
        $nestId = $this->di['array_get']($data, 'nest_id');
        if (!$nestId) {
            return [];
        }
        return $this->getService()->getEggs($nestId);
    }

    public function get_locations($data)
    {
        return $this->getService()->getLocations();
    }
}
