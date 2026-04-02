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




class Priyx_AppAdmin extends Priyx_App
{
    public function init()
    {
        $m = $this->di['mod']($this->mod);
        $controller = $m->getAdminController();
        $controller->register($this);
    }

    public function render($fileName, $variableArray = array())
    {
        $latte = $this->getLatte();
        $templateFile = $this->resolveTemplate($fileName . '.latte');

        if ($templateFile === null) {
            error_log("Template not found: $fileName");
            throw new \Priyx_Exception('Page not found', null, 404);
        }

        $params = $this->normalizeTemplateParams(array_merge($this->getGlobalParams(), $variableArray));

        try {
            return $latte->renderToString($templateFile, $params);
        } catch (\Exception $e) {
            error_log('Latte render error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function redirect($path)
    {
        $location = $this->di['url']->adminLink($path);
        header("Location: $location");
        exit;
    }

    protected function getGlobalParams()
    {
        $params = [];

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH']) {
            $_GET['ajax'] = true;
        }
        $params['request'] = $this->di['request'];
        $params['active_menu'] = null;
        $params['hide_menu'] = false;
        $params['product'] = null;

        $service = $this->di['mod_service']('theme');
        $theme = $service->getCurrentAdminAreaTheme();
        $params['theme'] = $theme;
        
        $params['guest'] = new Priyx_ViewApi($this->di['api_guest']);
        $params['lang'] = $this->di['config']['locale'] ?? 'en_US';
        $params['now'] = new \DateTime();
        $params['admin'] = null;

        if($this->di['auth']->isAdminLoggedIn()) {
            $params['admin'] = new Priyx_ViewApi($this->di['api_admin']);
        }

        return $params;
    }

    protected function normalizeTemplateParams(array $params)
    {
        foreach ($params as $key => $value) {
            $params[$key] = Priyx_ViewData::wrap($value);
        }

        return $params;
    }

    protected function resolveTemplate($name)
    {
        $service = $this->di['mod_service']('theme');
        $theme = $service->getCurrentAdminAreaTheme();
        $code = $theme['code'];

        $name = preg_replace('#/{2,}#', '/', str_replace('\\', '/', $name));

        // 1. Admin theme html/ directory
        $themePath = PS_PATH_ADMIN_THEMES . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . $name;
        if (file_exists($themePath)) {
            return $themePath;
        }

        // 2. Module html_admin/ directory
        $parts = explode('_', pathinfo($name, PATHINFO_FILENAME));
        if (count($parts) >= 2 && $parts[0] === 'mod') {
            $moduleName = ucfirst($parts[1]);
            $modulePath = PS_PATH_MODS . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'html_admin' . DIRECTORY_SEPARATOR . $name;
            if (file_exists($modulePath)) {
                return $modulePath;
            }
        }

        return null;
    }

    protected function getLatte()
    {
        $service = $this->di['mod_service']('theme');
        $theme = $service->getCurrentAdminAreaTheme();
        $code = $theme['code'];

        $latte = new \Latte\Engine();
        $latte->setTempDirectory(PS_PATH_CACHE . '/latte');

        $latte->setLoader(new Priyx_LatteLoader([
            'mods'  => PS_PATH_MODS,
            'theme' => PS_PATH_ADMIN_THEMES . DIRECTORY_SEPARATOR . $code,
            'type'  => 'admin',
        ]));

        $extensions = new Priyx_LatteExtensions();
        $extensions->setDi($this->di);
        $extensions->setCurrentTheme($code);
        $extensions->register($latte);

        return $latte;
    }
}
