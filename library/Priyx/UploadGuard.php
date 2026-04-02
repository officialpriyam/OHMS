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



class Priyx_UploadGuard
{
    private const DANGEROUS_EXTENSIONS = [
        'php',
        'php3',
        'php4',
        'php5',
        'phtml',
        'phar',
        'phps',
        'cgi',
        'pl',
        'py',
        'rb',
        'sh',
        'bash',
        'zsh',
        'bat',
        'cmd',
        'com',
        'exe',
        'dll',
        'msi',
        'asp',
        'aspx',
        'jsp',
        'jspx',
        'jar',
    ];

    private const THEME_ALLOWED_EXTENSIONS = [
        'css',
        'js',
        'map',
        'png',
        'jpg',
        'jpeg',
        'gif',
        'webp',
        'svg',
        'ico',
        'woff',
        'woff2',
        'ttf',
        'otf',
        'eot',
    ];

    private const DOWNLOADABLE_ALLOWED_EXTENSIONS = [
        '7z',
        'avi',
        'bz2',
        'csv',
        'doc',
        'docx',
        'epub',
        'flac',
        'gif',
        'gz',
        'iso',
        'jpeg',
        'jpg',
        'js',
        'json',
        'm4a',
        'mkv',
        'mobi',
        'mov',
        'mp3',
        'mp4',
        'ods',
        'odt',
        'odp',
        'pdf',
        'png',
        'ppt',
        'pptx',
        'rar',
        'tar',
        'tgz',
        'txt',
        'wav',
        'webp',
        'xls',
        'xlsx',
        'xz',
        'zip',
    ];

    private const THEME_ALLOWED_MIME_TYPES = [
        'css' => ['text/css', 'text/plain', 'application/octet-stream'],
        'js' => ['application/javascript', 'application/x-javascript', 'text/javascript', 'text/plain', 'application/octet-stream'],
        'map' => ['application/json', 'text/plain', 'application/octet-stream'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'svg' => ['image/svg+xml', 'text/plain'],
        'ico' => ['image/x-icon', 'image/vnd.microsoft.icon', 'application/octet-stream'],
        'woff' => ['font/woff', 'application/font-woff', 'application/x-font-woff', 'application/octet-stream'],
        'woff2' => ['font/woff2', 'application/octet-stream'],
        'ttf' => ['font/ttf', 'application/x-font-ttf', 'application/octet-stream'],
        'otf' => ['font/otf', 'application/vnd.ms-opentype', 'application/octet-stream'],
        'eot' => ['application/vnd.ms-fontobject', 'application/octet-stream'],
    ];

    private const BLOCKED_MIME_TYPES = [
        'application/x-httpd-php',
        'application/x-php',
        'text/x-php',
        'text/html',
        'application/xhtml+xml',
        'text/x-shellscript',
        'application/x-sh',
        'text/x-python',
        'text/x-perl',
        'application/x-msdownload',
        'application/x-dosexec',
    ];

    /**
     * @param array|Priyx_RequestFile $file
     */
    public function prepareThemeAssetUpload($file, string $targetFilename): array
    {
        $upload = $this->extractUpload($file);
        $targetFilename = $this->normalizeThemeAssetTarget($targetFilename);
        $extension = $this->getExtension($targetFilename);

        if (!in_array($extension, self::THEME_ALLOWED_EXTENSIONS, true)) {
            throw new Priyx_Exception('Theme asset type ":type" is not allowed', [':type' => $extension ?: 'unknown']);
        }

        $this->assertOriginalFilenameIsSafe($upload['name']);
        $this->assertThemeMimeType($upload['tmp_name'], $extension);

        if ('svg' === $extension) {
            $this->assertSvgIsSafe($upload['tmp_name']);
        }

        return [
            'tmp_name' => $upload['tmp_name'],
            'size' => $upload['size'],
            'mime_type' => $upload['mime_type'],
            'target_filename' => $targetFilename,
        ];
    }

    /**
     * @param array|Priyx_RequestFile $file
     */
    public function prepareDownloadableUpload($file): array
    {
        $upload = $this->extractUpload($file);
        $this->assertOriginalFilenameIsSafe($upload['name']);

        $displayName = $this->sanitizeDownloadableFilename($upload['name']);
        $extension = $this->getExtension($displayName);
        if (!in_array($extension, self::DOWNLOADABLE_ALLOWED_EXTENSIONS, true)) {
            throw new Priyx_Exception('Uploaded file type ":type" is not allowed', [':type' => $extension ?: 'unknown']);
        }

        $this->assertDownloadableMimeType($upload['tmp_name']);

        return [
            'tmp_name' => $upload['tmp_name'],
            'size' => $upload['size'],
            'mime_type' => $upload['mime_type'],
            'display_name' => $displayName,
            'storage_name' => bin2hex(random_bytes(16)),
        ];
    }

    public function movePreparedUpload(array $preparedUpload, string $targetPath): void
    {
        $directory = dirname($targetPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new Priyx_Exception('Failed to create upload directory');
        }

        if (!is_uploaded_file($preparedUpload['tmp_name'])) {
            throw new Priyx_Exception('Potentially unsafe upload rejected');
        }

        if (!move_uploaded_file($preparedUpload['tmp_name'], $targetPath)) {
            throw new Priyx_Exception('Failed to move uploaded file');
        }
    }

    /**
     * @param array|Priyx_RequestFile $file
     */
    private function extractUpload($file): array
    {
        if ($file instanceof Priyx_RequestFile) {
            $name = $file->getName();
            $tmpName = $file->getTmpName();
            $error = $file->getError();
            $size = $file->getSize();
        } elseif (is_array($file)) {
            $name = $file['name'] ?? '';
            $tmpName = $file['tmp_name'] ?? '';
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            $size = (int) ($file['size'] ?? 0);
        } else {
            throw new Priyx_Exception('Invalid upload payload');
        }

        if (UPLOAD_ERR_OK !== $error) {
            throw new Priyx_Exception('Error uploading file. :reason', [':reason' => $this->getUploadErrorMessage($error)]);
        }

        if (!is_string($tmpName) || '' === $tmpName || !file_exists($tmpName)) {
            throw new Priyx_Exception('Uploaded file is missing');
        }

        $mimeType = $this->detectMimeType($tmpName);

        return [
            'name' => (string) $name,
            'tmp_name' => $tmpName,
            'size' => (int) $size,
            'mime_type' => $mimeType,
        ];
    }

    private function normalizeThemeAssetTarget(string $targetFilename): string
    {
        $targetFilename = trim(str_replace('\\', '/', $targetFilename));
        $targetFilename = basename($targetFilename);

        if ('' === $targetFilename || str_contains($targetFilename, '..')) {
            throw new Priyx_Exception('Invalid theme asset target');
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $targetFilename)) {
            throw new Priyx_Exception('Theme asset filename contains unsupported characters');
        }

        return $targetFilename;
    }

    private function sanitizeDownloadableFilename(string $originalName): string
    {
        $originalName = trim(str_replace('\\', '/', $originalName));
        $originalName = basename($originalName);
        $originalName = preg_replace('/[\x00-\x1F\x7F]+/u', '', $originalName);
        $originalName = preg_replace('/\s+/u', ' ', $originalName);
        $originalName = preg_replace('/[^A-Za-z0-9._()\-\[\] @+]+/', '_', $originalName);

        $extension = $this->getExtension($originalName);
        if ('' === $extension) {
            throw new Priyx_Exception('Uploaded file must have an extension');
        }

        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = trim((string) $basename, " ._-\t\n\r\0\x0B");
        if ('' === $basename) {
            $basename = 'download';
        }

        return $basename . '.' . $extension;
    }

    private function assertOriginalFilenameIsSafe(string $originalName): void
    {
        $normalizedName = trim(str_replace('\\', '/', $originalName));
        $normalizedName = basename($normalizedName);

        if ('' === $normalizedName || str_contains($normalizedName, '..') || preg_match('/[\x00-\x1F\x7F]+/u', $normalizedName)) {
            throw new Priyx_Exception('Unsafe upload filename rejected');
        }

        if (str_starts_with($normalizedName, '.')) {
            throw new Priyx_Exception('Hidden upload filenames are not allowed');
        }

        $parts = array_values(array_filter(explode('.', strtolower($normalizedName)), static fn ($part) => '' !== $part));
        if (empty($parts)) {
            throw new Priyx_Exception('Uploaded file must have a valid name');
        }

        $extension = array_pop($parts);
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            throw new Priyx_Exception('Executable uploads are not allowed');
        }

        foreach ($parts as $part) {
            if (in_array($part, self::DANGEROUS_EXTENSIONS, true)) {
                throw new Priyx_Exception('Suspicious double-extension upload rejected');
            }
        }
    }

    private function assertThemeMimeType(string $tmpName, string $extension): void
    {
        $mimeType = $this->detectMimeType($tmpName);
        if (null === $mimeType) {
            return;
        }

        $allowedMimeTypes = self::THEME_ALLOWED_MIME_TYPES[$extension] ?? [];
        if (!empty($allowedMimeTypes) && !in_array($mimeType, $allowedMimeTypes, true)) {
            throw new Priyx_Exception('Theme asset MIME type is not allowed');
        }

        $this->assertMimeTypeIsNotBlocked($mimeType);
    }

    private function assertDownloadableMimeType(string $tmpName): void
    {
        $mimeType = $this->detectMimeType($tmpName);
        if (null === $mimeType) {
            return;
        }

        $this->assertMimeTypeIsNotBlocked($mimeType);
    }

    private function assertMimeTypeIsNotBlocked(string $mimeType): void
    {
        if (in_array(strtolower($mimeType), self::BLOCKED_MIME_TYPES, true)) {
            throw new Priyx_Exception('Uploaded file MIME type is not allowed');
        }
    }

    private function assertSvgIsSafe(string $tmpName): void
    {
        $svg = file_get_contents($tmpName);
        if (false === $svg) {
            throw new Priyx_Exception('Failed to inspect SVG asset');
        }

        if (preg_match('/<script\b|onload\s*=|onerror\s*=|javascript:|<foreignObject\b/i', $svg)) {
            throw new Priyx_Exception('SVG assets may not contain executable content');
        }
    }

    private function detectMimeType(string $tmpName): ?string
    {
        if (!class_exists('finfo')) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName);
        if (!is_string($mimeType) || '' === $mimeType) {
            return null;
        }

        return strtolower($mimeType);
    }

    private function getExtension(string $filename): string
    {
        return strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by an extension',
            default => 'Unknown upload error',
        };
    }
}
