<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media $media */
        $media = $this->resource;
        $tags = $media->getCustomProperty('tags');
        $data = [
            'id' => $media->id,
            'thumb' => $media->getUrl('thumb'),
            'url' => $media->getUrl(),
            'name' => $media->name,
            'size' => $media->size,
            'type' => $media->mime_type,
            'params' => [
                'description' => $media->getCustomProperty('description'),
                'tags' => is_string($tags) ? explode(', ', $tags) : $tags,
            ]
        ];
        $status = $media->getCustomProperty('status');
        if ($status === 'READY') {
            $media->file_name = pathinfo($media->file_name, PATHINFO_FILENAME) . '.mp4';
            $data['preview_url'] = $media->getUrl();
        }
        return $data;
    }
}
