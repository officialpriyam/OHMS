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

namespace Priyx\Mod\Updater\Api;

class Admin extends \Api_Abstract
{
    public function get_info($data)
    {
        return $this->di['mod_service']('updater')->getLatestUpdateInfo();
    }

    public function update($data)
    {
        $service = $this->di['mod_service']('updater');
        $info = $service->getLatestUpdateInfo();
        if (!$info || !isset($info['update_url'])) {
            throw new \Exception("Could not fetch update information");
        }

        return $service->applyUpdate($info['update_url']);
    }

    public function create_backup($data)
    {
        return $this->di['mod_service']('updater')->createBackup();
    }
}
