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




class Model_ClientPasswordResetTable implements \Priyx\InjectionAwareInterface
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

    public function generate(Model_Client $client, $ip)
    {
        $r = $this->di['db']->findOne('ClientPasswordReset', 'client_id', $client->id);
        if(!$r instanceof Model_ClientPasswordReset) {
            $r = $this->di['db']->dispense('ClientPasswordReset');
            $r->created_at  = date('Y-m-d H:i:s');
            $r->client_id   = $client->id;
        }
        
        $r->ip          = $ip;
        $r->hash        = sha1(rand(50, rand(10, 99)));
        $r->updated_at  = date('Y-m-d H:i:s');
        $this->di['db']->store($r);
        
        return $r;
    }
    
    public function rmByClient(Model_Client $client)
    {
        $models = $this->di['db']->find('ClientPasswordReset', 'client_id = ?', array($client->id));
        foreach($models as $model){
            $this->di['db']->trash($model);
        }
    }
    
}