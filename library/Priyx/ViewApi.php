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



class Priyx_ViewApi
{
    protected $api;

    public function __construct(Api_Handler $api)
    {
        $this->api = $api;
    }

    public function __call($method, $arguments)
    {
        return Priyx_ViewData::wrap($this->api->{$method}(...$arguments));
    }

    public function __get($name)
    {
        return $this->__call($name, []);
    }

    public function __isset($name)
    {
        try {
            return $this->__get($name) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
