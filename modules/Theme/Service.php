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



namespace Priyx\Mod\Theme;

use Priyx\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;
    protected array $orderformManifestCache = [];

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getTheme($name)
    {
        $theme = new \Priyx\Mod\Theme\Model\Theme($name);

        return $theme;
    }

    public function getCurrentThemePreset(Model\Theme $theme)
    {
        $current = $this->di['db']->getCell("SELECT meta_value
        FROM extension_meta
        WHERE 1
        AND extension = 'mod_theme'
        AND rel_id = 'current'
        AND rel_type = 'preset'
        AND meta_key = :theme",
            [':theme' => $theme->getName()]);
        if (empty($current)) {
            $current = $theme->getCurrentPreset();
            $this->setCurrentThemePreset($theme, $current);
        }

        return $current;
    }

    public function setCurrentThemePreset(Model\Theme $theme, $preset)
    {
        $params = ['theme' => $theme->getName(), 'preset' => $preset];
        $updated = $this->di['db']->exec("
            UPDATE extension_meta
            SET meta_value = :preset
            WHERE 1
            AND extension = 'mod_theme'
            AND rel_type = 'preset'
            AND rel_id = 'current'
            AND meta_key = :theme
            LIMIT 1
            ", $params);

        if (!$updated) {
            $updated = $this->di['db']->exec("
            INSERT INTO extension_meta (
                extension,
                rel_type,
                rel_id,
                meta_value,
                meta_key,
                created_at,
                updated_at
            )
            VALUES (
                'mod_theme',
                'preset',
                'current',
                :preset,
                :theme,
                NOW(),
                NOW()
            )
            ", $params);
        }

        return true;
    }

    public function deletePreset(Model\Theme $theme, $preset)
    {
        // delete settings
        $this->di['db']->exec("DELETE FROM extension_meta
            WHERE extension = 'mod_theme'
            AND rel_type = 'settings'
            AND rel_id = :theme
            AND meta_key = :preset",
            ['theme' => $theme->getName(), 'preset' => $preset]);

        // delete default preset
        $this->di['db']->exec("DELETE FROM extension_meta
            WHERE extension = 'mod_theme'
            AND rel_type = 'preset'
            AND rel_id = 'current'
            AND meta_key = :theme",
            ['theme' => $theme->getName()]);

        return true;
    }

    public function getThemePresets(Model\Theme $theme)
    {
        $presets = $this->di['db']->getAssoc("SELECT meta_key FROM extension_meta WHERE extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :key",
            ['key' => $theme->getName()]);

        // insert default presets to database
        if (empty($presets)) {
            $core_presets = $theme->getPresetsFromSettingsDataFile();
            $presets = [];
            foreach ($core_presets as $preset => $params) {
                $presets[$preset] = $preset;
                $this->updateSettings($theme, $preset, $params);
            }
        }

        // if theme does not have settings data file
        if (empty($presets)) {
            $presets = ['Default' => 'Default'];
        }

        return $presets;
    }

    public function getThemeSettings(Model\Theme $theme, $preset = null)
    {
        if (is_null($preset)) {
            $preset = $this->getCurrentThemePreset($theme);
        }

        $meta = $this->di['db']->findOne('ExtensionMeta',
            "extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :theme AND meta_key = :preset",
            ['theme' => $theme->getName(), 'preset' => $preset]);
        if ($meta) {
            return json_decode($meta->meta_value, 1);
        } else {
            return $theme->getPresetFromSettingsDataFile($preset);
        }
    }

    public function getOrderformList(?Model\Theme $theme = null): array
    {
        if (!$theme instanceof Model\Theme) {
            $theme = $this->getCurrentClientAreaTheme();
        }

        $cacheKey = $theme->getName();
        if (isset($this->orderformManifestCache[$cacheKey])) {
            return $this->orderformManifestCache[$cacheKey];
        }

        $path = $theme->getPathOrderforms();
        $orderforms = [];
        if (is_dir($path)) {
            $entries = scandir($path) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                    continue;
                }

                $directory = $path . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($directory)) {
                    continue;
                }

                $manifestFile = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
                $manifest = [
                    'code' => $entry,
                    'name' => ucfirst(str_replace(['-', '_'], ' ', $entry)),
                    'description' => '',
                    'thumbnail' => null,
                    'supports' => [],
                    'path' => $directory,
                ];

                if (is_file($manifestFile)) {
                    $decoded = json_decode((string) file_get_contents($manifestFile), true);
                    if (is_array($decoded)) {
                        $manifest = array_merge($manifest, $decoded);
                        $manifest['path'] = $directory;
                    }
                }

                $orderforms[$manifest['code']] = $manifest;
            }
        }

        if (!isset($orderforms['default'])) {
            $orderforms['default'] = [
                'code' => 'default',
                'name' => 'Default',
                'description' => 'Default orderform',
                'thumbnail' => null,
                'supports' => [],
                'path' => $path . DIRECTORY_SEPARATOR . 'default',
            ];
        }

        ksort($orderforms);
        $this->orderformManifestCache[$cacheKey] = $orderforms;

        return $orderforms;
    }

    public function getOrderformPairs(?Model\Theme $theme = null): array
    {
        $pairs = [];
        foreach ($this->getOrderformList($theme) as $code => $manifest) {
            $pairs[$code] = $manifest['name'] ?? ucfirst($code);
        }

        return $pairs;
    }

    public function getDefaultOrderformCode(?Model\Theme $theme = null): string
    {
        if (!$theme instanceof Model\Theme) {
            $theme = $this->getCurrentClientAreaTheme();
        }

        $settings = $this->getThemeSettings($theme);
        $default = trim((string) ($settings['default_orderform'] ?? ''));
        $orderforms = $this->getOrderformList($theme);

        if ($default !== '' && isset($orderforms[$default])) {
            return $default;
        }

        return 'default';
    }

    public function getEffectiveOrderformCode($category = null, ?Model\Theme $theme = null): string
    {
        if (!$theme instanceof Model\Theme) {
            $theme = $this->getCurrentClientAreaTheme();
        }

        $categoryCode = null;
        if ($category instanceof \Model_ProductCategory) {
            $categoryCode = $category->orderform_code ?? null;
        } elseif (is_array($category)) {
            $categoryCode = $category['orderform_code'] ?? null;
        } elseif ($category instanceof \ArrayAccess) {
            $categoryCode = $category['orderform_code'] ?? null;
        }

        $categoryCode = trim((string) $categoryCode);
        $orderforms = $this->getOrderformList($theme);
        if ($categoryCode !== '' && isset($orderforms[$categoryCode])) {
            return $categoryCode;
        }

        return $this->getDefaultOrderformCode($theme);
    }

    public function resolveOrderformTemplate(string $screen, ?string $orderformCode = null, ?Model\Theme $theme = null, string $ext = 'latte'): ?array
    {
        if (!$theme instanceof Model\Theme) {
            $theme = $this->getCurrentClientAreaTheme();
        }

        $screen = trim(str_replace('\\', '/', $screen), '/');
        $orderformCode = $orderformCode ?: $this->getDefaultOrderformCode($theme);
        $orderforms = $this->getOrderformList($theme);

        $candidates = [];
        if (isset($orderforms[$orderformCode])) {
            $candidates[] = $orderformCode;
        }
        if ($orderformCode !== 'default' && isset($orderforms['default'])) {
            $candidates[] = 'default';
        }

        foreach ($candidates as $candidate) {
            $path = $theme->getPathOrderforms() . DIRECTORY_SEPARATOR . $candidate . DIRECTORY_SEPARATOR . $screen . '.' . $ext;
            if (is_file($path)) {
                return [
                    'code' => $candidate,
                    'path' => $path,
                    'manifest' => $orderforms[$candidate] ?? ['code' => $candidate],
                ];
            }
        }

        return null;
    }

    public function uploadAssets(Model\Theme $theme, array $files)
    {
        $dest = $theme->getPathAssets().DIRECTORY_SEPARATOR;
        $uploadGuard = new \Priyx_UploadGuard();
        $allowedUploads = [];
        foreach ($theme->getSettingsPageFiles() as $uploadField) {
            $allowedUploads[(string) $uploadField] = true;
            $allowedUploads[str_replace('_', '.', (string) $uploadField)] = true;
        }

        foreach ($files as $filename => $f) {
            if (UPLOAD_ERR_NO_FILE == $f['error']) {
                continue;
            }

            $filename = str_replace('_', '.', $filename);
            if (!isset($allowedUploads[$filename])) {
                throw new \Priyx_Exception('Unexpected theme asset upload ":file" was rejected', [':file' => $filename]);
            }

            $preparedUpload = $uploadGuard->prepareThemeAssetUpload($f, $filename);
            $uploadGuard->movePreparedUpload($preparedUpload, $dest.$preparedUpload['target_filename']);
        }
    }

    public function updateSettings(Model\Theme $theme, $preset, array $params)
    {
        $meta = $this->di['db']->findOne('ExtensionMeta',
            "extension = 'mod_theme' AND rel_type = 'settings' AND rel_id = :theme AND meta_key = :preset",
            ['theme' => $theme->getName(), 'preset' => $preset]);

        if (!$meta) {
            $meta = $this->di['db']->dispense('ExtensionMeta');
            $meta->extension = 'mod_theme';
            $meta->rel_type = 'settings';
            $meta->rel_id = $theme->getName();
            $meta->meta_key = $preset;
            $meta->created_at = date('Y-m-d H:i:s');
        }

        $meta->meta_value = json_encode($params);
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);

        return true;
    }

    public function regenerateThemeSettingsDataFile(Model\Theme $theme)
    {
        $settings = [];
        $presets = $this->getThemePresets($theme);
        foreach ($presets as $preset) {
            $settings['presets'][$preset] = $this->getThemeSettings($theme, $preset);
        }
        $settings['current'] = $this->getCurrentThemePreset($theme);
        $data_file = $theme->getPathSettingsDataFile();

        $this->di['tools']->file_put_contents(json_encode($settings), $data_file);

        return true;
    }

    public function regenerateThemeCssAndJsFiles(Model\Theme $theme, $preset, $api_admin)
    {
        $assets = $theme->getPathAssets().DIRECTORY_SEPARATOR;

        $css_files = $this->di['tools']->glob($assets.'*.css.phtml');
        $js_files = $this->di['tools']->glob($assets.'*.js.phtml');
        $files = array_merge($css_files, $js_files);

        foreach ($files as $file) {
            $settings = $this->getThemeSettings($theme, $preset);
            $real_file = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_FILENAME);

            $vars = [];

            $vars['settings'] = $settings;
            $vars['_tpl'] = $this->di['tools']->file_get_contents($file);
            $systemService = $this->di['mod_service']('system');
            $data = $systemService->renderString($vars['_tpl'], false, $vars);

            $this->di['tools']->file_put_contents($data, $real_file);
        }

        return true;
    }

    public function getCurrentAdminAreaTheme()
    {
        $query = 'SELECT value
                FROM setting
                WHERE param = :param
               ';
        $default = 'admin_default';
        $theme = $this->di['db']->getCell($query, ['param' => 'admin_theme']);
        $path = PS_PATH_ADMIN_THEMES.DIRECTORY_SEPARATOR;
        if (null == $theme || !file_exists($path.$theme)) {
            $theme = $default;
        }
        $url = $this->di['config']['url'].'admin/template/'.$theme.'/';

        return ['code' => $theme, 'url' => $url];
    }

    public function getCurrentClientAreaTheme()
    {
        $code = $this->getCurrentClientAreaThemeCode();

        return $this->getTheme($code);
    }

    public function getCurrentClientAreaThemeCode()
    {
        if (defined('BB_THEME_CLIENT')) {
            $theme = BB_THEME_CLIENT;
        } else {
            $theme = $this->di['db']->getCell("SELECT value FROM setting WHERE param = 'theme' ");
        }

        return !empty($theme) ? $theme : 'OHMS';
    }

    public function getThemes($client = true)
    {
        $list = [];
        $path = $client ? PS_PATH_THEMES : PS_PATH_ADMIN_THEMES;
        $path .= DIRECTORY_SEPARATOR;
        if ($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                if (is_dir($path.DIRECTORY_SEPARATOR.$file) && '.' != $file[0]) {
                    try {
                        if (!$client && false !== strpos($file, 'admin')) {
                            $list[] = $this->_loadTheme($file);
                        }

                        if ($client && false === strpos($file, 'admin')) {
                            $list[] = $this->_loadTheme($file);
                        }
                    } catch (\Exception $e) {
                        error_log($e->getMessage());
                    }
                }
            }
        }

        return $list;
    }

    public function getThemeConfig($client = true, $mod = null)
    {
        if ($client) {
            $theme = $this->getCurrentClientAreaThemeCode();
        } else {
            $default = 'admin_default';
            $systemService = $this->di['mod_service']('system');
            $theme = $systemService->getParamValue('admin_theme', $default);
        }

        $path = $this->getThemesPath();
        if (!file_exists($path.$theme)) {
            $theme = $default;
        }

        return $this->_loadTheme($theme, $client, $mod);
    }

    public function loadTheme($code, $client = true, $mod = null)
    {
        return $this->_loadTheme($code, $client, $mod);
    }

    public function getThemesPath($client = true)
    {
        return ($client ? PS_PATH_THEMES : PS_PATH_ADMIN_THEMES).DIRECTORY_SEPARATOR;
    }

    private function _loadTheme($theme, $client = true, $mod = null)
    {
        $theme_path = $this->getThemesPath($client).$theme;

        if (!file_exists($theme_path)) {
            throw new \Priyx_Exception('Theme was not found in path :path', [':path' => $theme_path]);
        }
        $manifest = $theme_path.'/manifest.json';

        if (file_exists($manifest)) {
            $config = json_decode(file_get_contents($manifest), true);
        } else {
            $config = [
                'name' => $theme,
                'version' => '1.0',
                'description' => 'Theme',
                'author' => 'OHMS',
                'author_url' => 'https://www.OHMS.org',
            ];
        }

        if (!is_array($config)) {
            throw new \Priyx_Exception('Unable to decode theme manifest file :file', [':file' => $manifest]);
        }

        $paths = [$theme_path.'/html'];

        if (isset($config['extends'])) {
            $ext = trim($config['extends'], '/');
            $ext = str_replace('.', '', $ext);

            $config['url'] = $client ? PS_URL.'themes/'.$ext.'/' : PS_URL.'admin/template/'.$ext.'/';
            array_push($paths, $this->getThemesPath($client).$ext.'/html');
        } else {
            $config['url'] = $client ? PS_URL.'themes/'.$theme.'/' : PS_URL.'admin/template/'.$theme.'/';
        }

        // add installed modules paths
        $table = $this->di['mod_service']('extension');
        $list = $table->getCoreAndActiveModules();
        // add module folder to look for template
        if (!is_null($mod)) {
            $list[] = $mod;
        }
        $list = array_unique($list);
        foreach ($list as $mod) {
            $p = PS_PATH_MODS.DIRECTORY_SEPARATOR.ucfirst($mod).DIRECTORY_SEPARATOR;
            $p .= $client ? 'html_client' : 'html_admin';
            if (file_exists($p)) {
                array_push($paths, $p);
            }
        }

        $config['code'] = $theme;
        $config['paths'] = $paths;

        return $config;
    }
}
