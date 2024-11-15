<?php

namespace App\Services;

class ReleaseService extends S3Service
{
    public function __construct(?string $internalDisk=null, ?string $publicDisk=null)
    {
        $internalDisk ??= 's3-internal-builds';
        parent::__construct($internalDisk, $publicDisk);
    }
}
