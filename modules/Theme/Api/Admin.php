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



namespace Priyx\Mod\Theme\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of available client area themes.
     *
     * @return array
     */
    public function get_list($data)
    {
        $themes = $this->getService()->getThemes();

        return ['list' => $themes];
    }

    /**
     * Get list of available admin area themes.
     *
     * @return array
     */
    public function get_admin_list($data)
    {
        $themes = $this->getService()->getThemes(false);

        return ['list' => $themes];
    }

    /**
     * Get theme by code.
     *
     * @param string $code - theme code
     *
     * @return array
     */
    public function get($data)
    {
        $required = [
            'code' => 'Theme code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->loadTheme($data['code']);
    }

    /**
     * Set new theme as default.
     *
     * @param string $code - theme code
     *
     * @return bool
     */
    public function select($data)
    {
        $required = [
            'code' => 'Theme code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $theme = $this->getService()->getTheme($data['code']);

        $systemService = $this->di['mod_service']('system');
        if ($theme->isAdminAreaTheme()) {
            $systemService->setParamValue('admin_theme', $data['code']);
        } else {
            $systemService->setParamValue('theme', $data['code']);
        }

        $this->di['logger']->info('Changed default theme');

        return true;
    }

    /**
     * Delete theme preset.
     *
     * @param string $code   - theme code
     * @param string $preset - theme preset code
     *
     * @return bool
     */
    public function preset_delete($data)
    {
        $required = [
            'code' => 'Theme code is missing',
            'preset' => 'Theme preset name is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        $theme = $service->getTheme($data['code']);
        $service->deletePreset($theme, $data['preset']);

        return true;
    }

    /**
     * Select new theme preset.
     *
     * @param string $code   - theme code
     * @param string $preset - theme preset code
     *
     * @return bool
     */
    public function preset_select($data)
    {
        $required = [
            'code' => 'Theme code is missing',
            'preset' => 'Theme preset name is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $theme = $service->getTheme($data['code']);
        $service->setCurrentThemePreset($theme, $data['preset']);

        return true;
    }

    public function orderform_get_pairs($data)
    {
        $code = $this->di['array_get']($data, 'code', null);
        $theme = $code ? $this->getService()->getTheme($code) : $this->getService()->getCurrentClientAreaTheme();

        return $this->getService()->getOrderformPairs($theme);
    }

    public function orderform_get_list($data)
    {
        $code = $this->di['array_get']($data, 'code', null);
        $theme = $code ? $this->getService()->getTheme($code) : $this->getService()->getCurrentClientAreaTheme();

        return array_values($this->getService()->getOrderformList($theme));
    }
}
