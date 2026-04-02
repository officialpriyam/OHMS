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

abstract class Registrar_AdapterAbstract
{
    protected $_log = null;

    /**
     * Are we in test mode ?
     *
     * @var boolean
     */
    protected $_testMode = false;

    /**
     * Return array with configuration
     * Must be overriden in adapter class
     * @return array
     */
    public static function getConfig()
    {
        throw new Registrar_Exception('Domain registrar class did not implement configuration options method', 749);
    }

    /**
     * Return array of TLDs current Registar is capable to register
     * If function returns empty array, this registrar can register any TLD
     * @return array
     */
    abstract public function getTlds();

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function isDomainAvailable(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function isDomainCanBeTransfered(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function modifyNs(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function modifyContact(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function transferDomain(Registrar_Domain $domain);

    /**
     * Should return details of registered domain
     * If domain is not registered should throw Registrar_Exception
     * @return Registrar_Domain
     * @throws Registrar_Exception
     */
    abstract public function getDomainDetails(Registrar_Domain $domain);

    /**
     * Should return domain transfer code
     *
     * @return string
     * @throws Registrar_Exception
     */
    abstract public function getEpp(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function registerDomain(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function renewDomain(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function deleteDomain(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function enablePrivacyProtection(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function disablePrivacyProtection(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function lock(Registrar_Domain $domain);

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    abstract public function unlock(Registrar_Domain $domain);

    /**
     * Set Log
     *
     * @param Priyx_Log $log
     * @return Registrar_AdapterAbstract
     */
    public function setLog(Priyx_Log $log)
    {
        $this->_log = $log;
        return $this;
    }

    public function getLog()
    {
        $log = $this->_log;
        if(!$log instanceof Priyx_Log) {
            $log = new Priyx_Log();
            $log->addWriter(new Priyx_LogDb('Model_ActivitySystem'));
        }
        return $log;
    }

    public function enableTestMode()
    {
        $this->_testMode = true;
        return $this;
    }
}
