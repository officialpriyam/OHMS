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




class Priyx_AppClient extends Priyx_App
{
    protected function init()
    {
        if($this->mod == 'api') {
            define ('PS_MODE_API', TRUE);
            $m = $this->di['mod']($this->mod);
            $m->registerClientRoutes($this);
        } else {
            $extensionService = $this->di['mod_service']('extension');
            $activeModules = $extensionService->getCoreAndActiveModules();
            foreach ($activeModules as $modName) {
                $m = $this->di['mod']($modName);
                $m->registerClientRoutes($this);
            }

            //init index module manually
            $this->get('', 'get_index');
            $this->get('/', 'get_index');


            //init custom methods for undefined pages
            $this->get('/:page', 'get_custom_page', array('page' => '[a-z0-9-/.//]+'));
            $this->post('/:page', 'get_custom_page', array('page' => '[a-z0-9-/.//]+'));
        }
    }

    public function get_index()
    {
        $template = $this->resolveTemplate('mod_index_home.latte');
        if ($template !== null) {
            return $this->renderTemplateFile($template);
        }

        return $this->render('mod_index_dashboard');
    }

    public function get_custom_page($page)
    {
        $ext = $this->ext;
        if(strpos($page, '.') !== false) {
            $ext = substr($page, strpos($page, '.')+1);
            // Ignore static file requests
            if (in_array($ext, ['ico', 'png', 'jpg', 'jpeg', 'gif', 'css', 'js', 'svg', 'map'])) {
                header("HTTP/1.0 404 Not Found");
                exit;
            }
            $page = substr($page, 0, strpos($page, '.'));
        }
        $page = str_replace('/', '_', $page);
        $tpl = 'mod_page_'.$page;
        try {
            return $this->render($tpl, array('post'=>$_POST), $ext);
        } catch(Exception $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
            if(PS_DEBUG){
                error_log($e);
            }
        }
      $e = new \Priyx_Exception('Page :url not found', array(':url'=>$this->url), 404);
        
      header("HTTP/1.0 404 Not Found");
      return $this->render('404', array('exception'=>$e));
    }

    /**
     * @param string $fileName
     */
    public function render($fileName, $variableArray = array(), $ext = 'latte')
    {
        $templateFile = $this->resolveTemplate($fileName . '.' . $ext);

        if ($templateFile === null) {
            error_log("Template not found: $fileName.$ext");
            throw new \Priyx_Exception('Page not found', null, 404);
        }

        return $this->renderTemplateFile($templateFile, $variableArray);
    }

    public function renderOrderform(string $screen, array $variableArray = [], array $context = [], string $ext = 'latte')
    {
        $themeService = $this->di['mod_service']('theme');
        $theme = $themeService->getCurrentClientAreaTheme();
        $category = $this->resolveOrderformCategoryContext($context);
        $orderformCode = $themeService->getEffectiveOrderformCode($category, $theme);
        $resolved = $themeService->resolveOrderformTemplate($screen, $orderformCode, $theme, $ext);

        if ($resolved === null) {
            return null;
        }

        $variableArray['current_orderform_code'] = $resolved['code'];
        $variableArray['current_orderform'] = $resolved['manifest'] ?? ['code' => $resolved['code']];
        $variableArray['orderform_category'] = $category;

        return $this->renderTemplateFile($resolved['path'], $variableArray);
    }

    /**
     * Get global template parameters (replaces Twig globals)
     */
    protected function getGlobalParams()
    {
        $params = [];

        // Request globals
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH']) {
            $_GET['ajax'] = true;
        }
        $params['request'] = $this->di['request'];
        $params['lang'] = $this->di['config']['locale'] ?? 'en_US';
        $params['now'] = new \DateTime();

        // Theme settings
        $service = $this->di['mod_service']('theme');
        $systemService = $this->di['mod_service']('system');
        $code = $service->getCurrentClientAreaThemeCode();
        $theme = $service->getTheme($code);
        $settings = $service->getThemeSettings($theme);
        if (!is_array($settings)) {
            $settings = [];
        }
        $company = $systemService->getCompany();

        // Client templates read these values from theme settings, so provide
        // system fallbacks to avoid undefined-property warnings.
        $settings['company_name'] = $settings['company_name'] ?? ($company['name'] ?? null);
        $settings['company_note'] = $settings['company_note'] ?? ($company['note'] ?? null);
        $settings['logo_url'] = $settings['logo_url'] ?? ($company['logo_url'] ?? null);

        $params['current_theme'] = $code;
        $params['settings'] = $settings;

        // Client globals
        if($this->di['auth']->isClientLoggedIn()) {
            $params['client'] = new Priyx_ViewApi($this->di['api_client']);
        } else {
            $params['client'] = null;
        }

        // Admin globals
        if($this->di['auth']->isAdminLoggedIn()) {
            $params['admin'] = new Priyx_ViewApi($this->di['api_admin']);
        } else {
            $params['admin'] = null;
        }

        $params['guest'] = new Priyx_ViewApi($this->di['api_guest']);

        return $params;
    }

    protected function normalizeTemplateParams(array $params)
    {
        foreach ($params as $key => $value) {
            $params[$key] = Priyx_ViewData::wrap($value);
        }

        return $params;
    }

    /**
     * Resolve template file path - looks in theme html/ first, then module html_client/
     */
    protected function resolveTemplate($name)
    {
        $service = $this->di['mod_service']('theme');
        $code = $service->getCurrentClientAreaThemeCode();

        // Normalize name
        $name = preg_replace('#/{2,}#', '/', str_replace('\\', '/', $name));

        // 1. Look in theme html/ directory
        $themePath = PS_PATH_THEMES . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . $name;
        if (file_exists($themePath)) {
            return $themePath;
        }

        // 2. Look in module html_client/ directory based on filename prefix
        $name_split = explode('_', $name);
        if (isset($name_split[1])) {
            $modulePath = PS_PATH_MODS . DIRECTORY_SEPARATOR . ucfirst($name_split[1]) . DIRECTORY_SEPARATOR . 'html_client' . DIRECTORY_SEPARATOR . $name;
            if (file_exists($modulePath)) {
                return $modulePath;
            }
        }

        return null;
    }

    protected function renderTemplateFile(string $templateFile, array $variableArray = [])
    {
        $latte = $this->getLatte();
        $params = $this->normalizeTemplateParams(array_merge($this->getGlobalParams(), $variableArray));

        try {
            return $latte->renderToString($templateFile, $params);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    protected function resolveOrderformCategoryContext(array $context)
    {
        if (isset($context['category']) && $context['category']) {
            return $context['category'];
        }

        if (isset($context['product']) && is_array($context['product'])) {
            $product = $context['product'];
            if (!empty($product['category']) && is_array($product['category'])) {
                return $product['category'];
            }

            $categoryId = $product['product_category_id'] ?? null;
            if ($categoryId) {
                try {
                    return $this->di['api_guest']->product_category_get(['id' => $categoryId]);
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
            }
        }

        if (!empty($context['category_id'])) {
            try {
                return $this->di['api_guest']->product_category_get(['id' => $context['category_id']]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return null;
    }

    protected function getLatte()
    {
        $service = $this->di['mod_service']('theme');
        $code = $service->getCurrentClientAreaThemeCode();

        $latte = new \Latte\Engine();
        $latte->setTempDirectory(PS_PATH_CACHE . '/latte');

        // Set up the file loader with template directory
        $themePath = PS_PATH_THEMES . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'html';
        $latte->setLoader(new Priyx_LatteLoader([
            'mods'  => PS_PATH_MODS,
            'theme' => PS_PATH_THEMES . DIRECTORY_SEPARATOR . $code,
            'type'  => 'client',
        ]));

        // Register custom extensions
        $extensions = new Priyx_LatteExtensions();
        $extensions->setDi($this->di);
        $extensions->setCurrentTheme($code);
        $extensions->register($latte);

        return $latte;
    }
}
