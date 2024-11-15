<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3Service
{
    public const S3_CUSTOM_VERSION_META = 'x-amz-meta-version';

    protected string $internalDisk;
    protected ?string $publicDisk;
    protected string $internalBucket;
    protected ?string $publicBucket;

    public function __construct(string $internalDisk, ?string $publicDisk=null)
    {
        $this->setInternalDisk($internalDisk);

        if(isset($publicDisk)){
            $this->setPublicDisk($publicDisk);
        }
    }

    public function setPublicDisk(string $disk) : void
    {
        $this->publicDisk = $disk;
        $this->publicBucket = config('filesystems.disks.'.$this->publicDisk.'.bucket');
    }

    public function setInternalDisk(string $disk) : void
    {
        $this->internalDisk = $disk;
        $this->internalBucket = config('filesystems.disks.'.$this->internalDisk.'.bucket');
    }

    public function listing(array $params, string $dir = '/') : array
    {
        $internal = is_bool($params['internal'])
            ? $params['internal']
            : $params['internal'] === 'true';

        return $internal
            ? $this->getS3FileUrls($this->internalDisk, $dir, $this->internalBucket)
            : $this->getS3FileUrls($this->publicDisk, $dir, $this->publicBucket);
    }


    public function store(array $params = []) : void
    {
        $disk = $params['internal'] ? $this->internalDisk : $this->publicDisk;
        $file = $params['file'];
        $ext = '.'.$file->extension();
        $name = $params['name'] . (Str::endsWith($params['name'], $ext) ? '' : $ext);

        $file->storeAs('/', $name, [
            'disk' => $disk,
            'Metadata' => ['version' => $params['version']]
        ]);
    }

    public function update(array $params = [])
    {

    }

    public function destroy()
    {
    }

    protected function getS3FileUrls(string $disk, string $dir, string $bucket) : array
    {
        /** @var \Illuminate\Filesystem\AwsS3V3Adapter $storageDisk */
        $storageDisk = Storage::disk($disk);
        $files = $storageDisk->files($dir, true);
        $fileData = [];

        foreach($files as $file){
            $meta = $this->getS3CustomMetadata($storageDisk->getClient(), $file, $bucket);
            $fileData []= [
                'url' => $storageDisk->url($file),
                'version' =>  $meta[static::S3_CUSTOM_VERSION_META] ?? null
            ];
        }

        return $fileData;
    }

    protected function getS3CustomMetadata(S3Client $client, string $file, string $bucket) : array
    {
        $meta = $this->getS3Metadata($client, $file, $bucket);
        return $meta['@metadata']['headers'] ?? [];
    }

    protected function getS3Metadata(S3Client $client, string $file, string $bucket) : array
    {
        $headers = $client->headObject(["Bucket" => $bucket, "Key" => $file]);
        return $headers->toArray();
    }
}
