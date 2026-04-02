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



namespace Priyx\Mod\Page;

use Priyx\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Priyx_Di
     */
    protected $di = null;

    /**
     * @param \Priyx_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Priyx_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getPairs()
    {
        $themeService = $this->di['mod_service']('theme');
        $code = $themeService->getCurrentClientAreaThemeCode();
        $paths = [
            PS_PATH_THEMES.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR.'html'.DIRECTORY_SEPARATOR,
            PS_PATH_MODS.DIRECTORY_SEPARATOR.'mod_page'.DIRECTORY_SEPARATOR.'html_client'.DIRECTORY_SEPARATOR,
        ];

        $list = [];
        foreach ($paths as $path) {
            foreach (glob($path.'mod_page_*.phtml') as $file) {
                $file = str_replace('mod_page_', '', pathinfo($file, PATHINFO_FILENAME));
                $list[$file] = ucwords(strtr($file, ['-' => ' ', '_' => ' ']));
            }
        }

        return $list;
    }
}
