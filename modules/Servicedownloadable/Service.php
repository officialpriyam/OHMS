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



namespace Priyx\Mod\Servicedownloadable;

use Priyx\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    private const STORAGE_SEPARATOR = '::';

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

    public function attachOrderConfig(\Model_Product $product, array &$data)
    {
        $c = json_decode((string) $product->config, 1);
        if (!is_array($c)) {
            $c = [];
        }
        $required = [
            'filename' => 'Product is not configured completely.',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $c);

        $data['filename'] = $c['filename'];
        if (!empty($c['storage_name'])) {
            $data['storage_name'] = $c['storage_name'];
        }

        return array_merge($c, $data);
    }

    public function validateOrderData(array &$data)
    {
        $required = [
            'filename' => 'Filename is missing in product config',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
    }

    /**
     * @return \Model_ServiceDownloadable
     */
    public function action_create(\Model_ClientOrder $order)
    {
        $c = json_decode((string) $order->config, 1);
        if (!is_array($c)) {
            throw new \Priyx_Exception(sprintf('Order #%s config is missing', $order->id));
        }
        $this->validateOrderData($c);

        $model = $this->di['db']->dispense('ServiceDownloadable');
        $model->client_id = $order->client_id;
        $model->filename = $this->packStoredFilename($c['filename'], $c['storage_name'] ?? null);
        $model->downloads = 0;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function action_activate(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_renew(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_suspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_unsuspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_cancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_uncancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return void
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof \Model_ServiceDownloadable) {
            $this->di['db']->trash($service);
        }
    }

    public function hitDownload(\Model_ServiceDownloadable $model)
    {
        ++$model->downloads;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }

    public function toApiArray(\Model_ServiceDownloadable $model, $deep = false, $identity = null)
    {
        $productService = $this->di['mod_service']('product');
        $fileInfo = $this->unpackStoredFilename($model->filename);
        $result = [
            'path' => $productService->getSavePath($fileInfo['storage_name'] ?: $fileInfo['filename']),
            'filename' => $fileInfo['filename'],
        ];

        if ($identity instanceof \Model_Admin) {
            $result['downloads'] = $model->downloads;
        }

        return $result;
    }

    public function uploadProductFile(\Model_Product $productModel)
    {
        $request = $this->di['request'];
        if (0 == $request->hasFiles()) {
            throw new \Priyx_Exception('Error uploading file');
        }
        $files = $request->getUploadedFiles();
        $file = $files[0];
        $uploadGuard = new \Priyx_UploadGuard();
        $preparedUpload = $uploadGuard->prepareDownloadableUpload($file);

        $productService = $this->di['mod_service']('product');
        $targetPath = $productService->getSavePath($preparedUpload['storage_name']);
        $uploadGuard->movePreparedUpload($preparedUpload, $targetPath);

        $config = json_decode((string) $productModel->config, 1);
        if (!is_array($config)) {
            $config = [];
        }
        $productService->removeOldFile($config);

        $config['filename'] = $preparedUpload['display_name'];
        $config['storage_name'] = $preparedUpload['storage_name'];
        $productModel->config = json_encode($config);
        $productModel->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($productModel);

        $this->di['logger']->info('Uploaded new file for product %s', $productModel->id);

        return true;
    }

    /**
     * @return bool
     *
     * @throws \Priyx_Exception
     */
    public function updateProductFile(\Model_ServiceDownloadable $serviceDownloadable, \Model_ClientOrder $order)
    {
        $request = $this->di['request'];
        if (0 == $request->hasFiles()) {
            throw new \Priyx_Exception('Error uploading file');
        }
        $productService = $this->di['mod_service']('product');
        $files = $request->getUploadedFiles();
        $file = $files[0];
        $uploadGuard = new \Priyx_UploadGuard();
        $preparedUpload = $uploadGuard->prepareDownloadableUpload($file);
        $currentFile = $this->unpackStoredFilename($serviceDownloadable->filename);
        $currentPath = $productService->getSavePath($currentFile['storage_name'] ?: $currentFile['filename']);
        if ($this->di['tools']->fileExists($currentPath)) {
            $this->di['tools']->unlink($currentPath);
        }

        $targetPath = $productService->getSavePath($preparedUpload['storage_name']);
        $uploadGuard->movePreparedUpload($preparedUpload, $targetPath);

        $serviceDownloadable->filename = $this->packStoredFilename($preparedUpload['display_name'], $preparedUpload['storage_name']);
        $serviceDownloadable->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($serviceDownloadable);

        $this->di['logger']->info('Uploaded new file for order %s', $order->id);

        return true;
    }

    private function _error_message($error_code)
    {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    public function sendDownload($filename, $path)
    {
        if (APPLICATION_ENV == 'testing') {
            return;
        }

        $downloadFilename = str_replace(["\r", "\n", '"'], '', basename((string) $filename));
        if ('' === $downloadFilename) {
            $downloadFilename = 'download';
        }

        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="' . $downloadFilename . '";');
        header('Content-Transfer-Encoding: binary');
        readfile($path);
        flush();
    }

    public function sendFile(\Model_ServiceDownloadable $serviceDownloadable)
    {
        $info = $this->toApiArray($serviceDownloadable);
        $filename = $info['filename'];
        $path = $info['path'];
        if (!$this->di['tools']->fileExists($path)) {
            throw new \Priyx_Exception('File can not be downloaded at the moment. Please contact support', null, 404);
        }
        $this->hitDownload($serviceDownloadable);
        $this->sendDownload($filename, $path);

        $this->di['logger']->info('Downloaded service %s file', $serviceDownloadable->id);

        return true;
    }

    private function packStoredFilename(string $filename, ?string $storageName): string
    {
        $filename = trim($filename);
        if (null === $storageName || '' === trim($storageName)) {
            return $filename;
        }

        return trim($storageName) . self::STORAGE_SEPARATOR . $filename;
    }

    private function unpackStoredFilename(string $storedFilename): array
    {
        $storedFilename = trim($storedFilename);
        if ('' === $storedFilename) {
            return ['storage_name' => null, 'filename' => ''];
        }

        if (!str_contains($storedFilename, self::STORAGE_SEPARATOR)) {
            return ['storage_name' => null, 'filename' => $storedFilename];
        }

        [$storageName, $filename] = explode(self::STORAGE_SEPARATOR, $storedFilename, 2);
        if ('' === trim($storageName) || '' === trim($filename)) {
            return ['storage_name' => null, 'filename' => $storedFilename];
        }

        return [
            'storage_name' => trim($storageName),
            'filename' => trim($filename),
        ];
    }
}
