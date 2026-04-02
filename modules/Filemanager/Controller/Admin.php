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

namespace Priyx\Mod\Filemanager\Controller;

class Admin implements \Priyx\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function register(\Priyx_App &$app)
    {
        $app->get('/filemanager/ide', 'get_ide', [], get_class($this));
        $app->get('/filemanager/icons', 'get_icons', [], get_class($this));
    }

    public function get_ide(\Priyx_App $app)
    {
        return '<html><body style="font-family:sans-serif; text-align:center; padding:50px;">
                <h2>File Manager Not Found</h2>
                <p>The File Manager module files are missing from this installation.</p>
                <p>Please use your hosting control panel (cPanel/DirectAdmin) "File Manager" to edit files directly.</p>
                <button onclick="window.history.back()">Go Back</button>
                </body></html>';
    }

    public function get_icons(\Priyx_App $app)
    {
        return '<html><body style="font-family:sans-serif; text-align:center; padding:50px;">
                <h2>Icon Browser Not Found</h2>
                <p>The File Manager module (which provides the icon browser) is missing.</p>
                <p>Please enter the icon URL manually in the input field.</p>
                <button onclick="window.history.back()">Go Back</button>
                </body></html>';
    }
}
