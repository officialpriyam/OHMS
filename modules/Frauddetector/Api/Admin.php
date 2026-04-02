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



namespace Priyx\Mod\Frauddetector\Api;

class Admin extends \Api_Abstract
{
    public function log_get_list($data)
    {
        return $this->getService()->getLogList($data);
    }

    public function log_delete($data)
    {
        $required = [
            'id' => 'Log ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->deleteLog((int) $data['id']);
    }

    public function clear_logs($data)
    {
        $days = isset($data['days']) && $data['days'] !== '' ? (int) $data['days'] : null;

        return $this->getService()->clearLogs($days);
    }
}
