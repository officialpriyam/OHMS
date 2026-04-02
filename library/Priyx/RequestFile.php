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




class Priyx_RequestFile extends SplFileInfo
{
    protected $name;
    protected $error;
    protected $size;
    protected $clientMediaType;


    public function __construct(array $file)
    {
        $this->name = $file['name'] ?? '';
        $this->error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        $this->size = (int) ($file['size'] ?? 0);
        $this->clientMediaType = $file['type'] ?? null;
        parent::__construct($file['tmp_name'] ?? '');
    }


    public function getName()
    {
        return $this->name;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientMediaType(): ?string
    {
        return is_string($this->clientMediaType) ? $this->clientMediaType : null;
    }

    public function getTmpName(): string
    {
        return $this->getPathname();
    }
}
