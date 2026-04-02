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




class Priyx_Validate
{

    protected $di = null;

    /**
     * @param Priyx_Di|null $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Priyx_Di|null
     */
    public function getDi()
    {
        return $this->di;
    }


    public function isSldValid($sld)
    {
        if (!is_string($sld)) {
            return false;
        }

        $sld = strtolower(trim($sld));
        if ('' === $sld || strlen($sld) > 63 || str_contains($sld, '.')) {
            return false;
        }

        if (!preg_match('/^(?:xn--)?[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $sld)) {
            return false;
        }

        if (!str_starts_with($sld, 'xn--') && strlen($sld) >= 4 && '--' === substr($sld, 2, 2)) {
            return false;
        }

        return true;
    }

    public function isEmailValid($email, $throw = true)
    {
        $email = is_string($email) ? trim($email) : '';
        $valid = false;

        if ('' !== $email && strlen($email) <= 254 && false !== filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $domain = substr(strrchr($email, '@') ?: '', 1);
            $valid = $this->isDomainNameValid($domain);
        }

        if(!$valid && $throw) {
            throw new \Priyx_Exception('Email is not valid');
        }
        return $valid;
    }
    
    public function isPasswordStrong($pwd)
    {
        if( strlen($pwd) < 7 ) {
            throw new \Priyx_Exception("Password too short!");
        }

        if( !preg_match("#[0-9]+#", $pwd) ) {
            throw new \Priyx_Exception("Password must include at least one number!");
        }

        if( !preg_match("#[a-z]+#", $pwd) ) {
            throw new \Priyx_Exception("Password must include at least one letter!");
        }

        /*
        if( !preg_match("#[A-Z]+#", $pwd) ) {
            throw new \Priyx_Exception("Password must include at least one CAPS!");
        }

        if( !preg_match("#\W+#", $pwd) ) {
            throw new \Priyx_Exception("Password must include at least one symbol!");
        }
        */
        return true;
    }

    /**
     * @param array $required - Array with required keys and messages to show if the key is not found
     * @param array $data - Array to search for keys
     * @param array $variables - Array of variables for message placeholders (:placeholder)
     * @param integer $code - Exception code
     * @throws Priyx_Exception
     */
    public function checkRequiredParamsForArray(array $required, array $data, ?array $variables = null, $code = 0)
    {
        foreach ($required as $key => $msg) {

            if(!isset($data[$key])){
                throw new \Priyx_Exception($msg, $variables, $code);
            }

            if (is_string($data[$key]) && strlen(trim($data[$key])) === 0){
                throw new \Priyx_Exception($msg, $variables, $code);
            }

            if (!is_numeric($data[$key]) && empty($data[$key])){
                throw new \Priyx_Exception($msg, $variables, $code);
            }
        }
    }

    public function isBirthdayValid($birthday = '')
    {
        if (strlen(trim($birthday)) > 0 && strtotime($birthday) === false) {
            throw new \Priyx_Exception('Invalid birth date value');
        }
        return true;
    }

    private function isDomainNameValid($domain)
    {
        if (!is_string($domain)) {
            return false;
        }

        $domain = strtolower(trim($domain));
        if ('' === $domain || strlen($domain) > 253) {
            return false;
        }

        return (bool) preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:xn--[a-z0-9-]{2,59}|[a-z]{2,63})$/', $domain);
    }
}
