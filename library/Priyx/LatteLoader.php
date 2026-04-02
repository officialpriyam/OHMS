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



class Priyx_LatteLoader implements \Latte\Loader
{
    protected $paths = [];
    protected $modsPath;
    protected $themePath;
    protected $type;

    /**
     * @param array $config ['mods' => ..., 'theme' => ..., 'type' => 'client'|'admin']
     */
    public function __construct(array $config)
    {
        $this->modsPath  = $config['mods'];
        $this->themePath = $config['theme'];
        $this->type      = $config['type'] ?? 'client';

        // Add theme html/ as primary search path
        $this->paths[] = $this->themePath . DIRECTORY_SEPARATOR . 'html';
    }

    /**
     * Returns template source code.
     */
    public function getContent(string $name): string
    {
        $file = $this->resolvePath($name);
        if ($file === null) {
            throw new \RuntimeException("Template '$name' not found.");
        }
        return file_get_contents($file);
    }

    /**
     * Check if template is expired (for caching). 
     */
    public function isExpired(string $name, int $time): bool
    {
        $file = $this->resolvePath($name);
        if ($file === null) {
            return true;
        }
        return filemtime($file) > $time;
    }

    /**
     * Returns referred template name.
     * Resolves relative includes from a parent template.
     */
    public function getReferredName(string $name, string $referringName): string
    {
        // If the name starts with a path separator or has a subdirectory, keep it as-is
        if ($name[0] === '/' || $name[0] === '\\') {
            return ltrim($name, '/\\');
        }

        // If referring name has a directory part, resolve relative to it
        $dir = dirname($referringName);
        if ($dir !== '.' && $dir !== '') {
            $resolved = $dir . '/' . $name;
            // Check if this resolved path exists before returning it
            if ($this->resolvePath($resolved) !== null) {
                return $resolved;
            }
        }

        return $name;
    }

    /**
     * Returns unique identifier for the template.
     */
    public function getUniqueId(string $name): string
    {
        $file = $this->resolvePath($name);
        return $file ?? $name;
    }

    /**
     * Resolve a template name to an absolute file path.
     *
     * Search order:
     * 1. Theme html/ directory (with subdirectory support)
     * 2. Module html_client/ directory (based on filename convention: mod_<module>_<action>.latte)
     */
    protected function resolvePath(string $name): ?string
    {
        // If it's already an absolute path (passed by AppAdmin/AppClient) and exists, return it
        if (is_file($name)) {
            return $name;
        }

        // Normalize separators
        $name = str_replace(['\\', '//'], ['/', '/'], $name);
        $name = ltrim($name, '/');

        // 1. Look in theme html/ directory
        foreach ($this->paths as $path) {
            $file = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (file_exists($file)) {
                return $file;
            }
        }

        // 2. Look in module directory based on filename prefix convention
        // e.g., mod_formbuilder_build.latte -> modules/Formbuilder/html_client/mod_formbuilder_build.latte
        $basename = basename($name);
        $parts = explode('_', pathinfo($basename, PATHINFO_FILENAME));
        if (count($parts) >= 2 && $parts[0] === 'mod') {
            $moduleName = ucfirst($parts[1]);
            $subDir = ($this->type === 'client') ? 'html_client' : 'html_admin';
            $modulePath = $this->modsPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . $basename;
            if (file_exists($modulePath)) {
                return $modulePath;
            }
        }

        return null;
    }
}
