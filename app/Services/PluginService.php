<?php

namespace App\Services;

class PluginService extends S3Service
{
    public function __construct(?string $internalDisk=null, ?string $publicDisk=null)
    {
        $internalDisk ??= 's3-internal-plugin';
        $publicDisk ??= 's3-plugin';
        parent::__construct($internalDisk, $publicDisk);
    }
}
