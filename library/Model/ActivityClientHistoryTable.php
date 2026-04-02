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




class Model_ActivityClientHistoryTable implements \Priyx\InjectionAwareInterface
{
    /**
     * @var \Priyx_Di
     */
    protected $di;

    /**
     * @param Priyx_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Priyx_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param array $data
     */
    public function logEvent($data)
    {
        if(!isset($data['client_id']) || !isset($data['ip'])) {
            return ;
        }

        $entry = $this->di['db']->dispense('ActivityClientHistory');
        $entry->client_id       = $data['client_id'];
        $entry->ip              = $data['ip'];
        $entry->created_at      = date('Y-m-d H:i:s');
        $entry->updated_at      = date('Y-m-d H:i:s');
        $this->di['db']->store($entry);
    }

    public function rmByClient(Model_Client $client)
    {
        $models = $this->di['db']->find('ActivityClientHistory', 'client_id = ?', array($client->id));
        foreach($models as $model){
            $this->di['db']->trash($model);
        }
    }

}