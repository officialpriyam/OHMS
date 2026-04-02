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

namespace Priyx\Mod\Updater;

class Service implements \Priyx\InjectionAwareInterface
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

    public function getSettings()
    {
        return $this->di['mod_config']('updater');
    }

    public function getLatestUpdateInfo($force = false)
    {
        $cacheKey = 'updater_info';
        $lastCheckKey = 'updater_last_check';
        
        $config = $this->getSettings();
        $lastCheck = $config[$lastCheckKey] ?? 0;
        
        // Cache for 24 hours unless forced
        if (!$force && (time() - $lastCheck < 86400) && isset($config[$cacheKey])) {
            return $config[$cacheKey];
        }

        $url = 'https://ohmsupdates.priyxstudio.in/latest';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return false;
        }

        $info = json_decode($response, true);
        
        // Save to config for caching
        $this->di['mod_config_update']('updater', [
            $cacheKey => $info,
            $lastCheckKey => time()
        ]);

        return $info;
    }

    public function isUpdateAvailable()
    {
        $info = $this->getLatestUpdateInfo();
        if (!$info || !isset($info['version'])) {
            return false;
        }
        
        $currentVersion = '1.0.1'; // Ideally fetched from guest->system_version()
        return version_compare($info['version'], $currentVersion, '>');
    }

    public function createBackup()
    {
        $backupDir = PS_PATH_ROOT . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . DIRECTORY_SEPARATOR . 'backup_' . $timestamp . '.zip';

        // Zip Database and Files
        $zip = new \ZipArchive();
        if ($zip->open($backupFile, \ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Cannot create backup zip file");
        }

        // Backup DB
        $dbBackup = $this->getDbBackupContent();
        $zip->addFromString('database.sql', $dbBackup);

        // Backup Files (excluding backups and cache)
        $rootPath = realpath(PS_PATH_ROOT);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Skip backups and cache
                if (strpos($relativePath, 'backups') === 0 || strpos($relativePath, 'cache') === 0) {
                    continue;
                }

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return $backupFile;
    }

    private function getDbBackupContent()
    {
        $pdo = $this->di['pdo'];
        $sql = "-- OHMS Database Backup\n\n";
        
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $createTable = $pdo->query("SHOW CREATE TABLE $table")->fetch(\PDO::FETCH_ASSOC);
            $sql .= "\n\n" . $createTable['Create Table'] . ";\n\n";
            
            $rows = $pdo->query("SELECT * FROM $table")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_values($row);
                $escapedValues = array_map(function($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, $values);
                
                $sql .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
            }
        }
        
        return $sql;
    }

    public function applyUpdate($updateUrl)
    {
        // 1. Backup
        $this->createBackup();

        // 2. Download
        $tempFile = tempnam(sys_get_temp_dir(), 'update');
        file_put_contents($tempFile, fopen($updateUrl, 'r'));

        // 3. Extract
        $zip = new \ZipArchive();
        if ($zip->open($tempFile) === TRUE) {
            $zip->extractTo(PS_PATH_ROOT);
            $zip->close();
        } else {
            throw new \Exception("Failed to open update package");
        }

        unlink($tempFile);
        
        // 4. Run migrations if update.php exists
        if (file_exists(PS_PATH_ROOT . '/update.php')) {
            require_once PS_PATH_ROOT . '/update.php';
        }

        return true;
    }
}
