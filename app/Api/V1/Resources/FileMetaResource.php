<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileMetaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $mediaMeta = $this->resource;
        $mediaMeta->data = is_string($mediaMeta->data) ? json_decode($mediaMeta->data, true) : $mediaMeta->data;
        $tags = $mediaMeta->data['tags'];
        return [
            'id' => $mediaMeta->id,
            'thumb' => $mediaMeta->thumbnail,
            'name' => $mediaMeta->name,
            'file_name' => $mediaMeta->file_name,
            'path' => $mediaMeta->path,
            'filepath' => $mediaMeta->filepath,
            'size' => $mediaMeta->size,
            'type' => $mediaMeta->mime_type,
            'file_last_updated_at' => $mediaMeta->file_last_updated_at,
            'params' => [
                'description' => $mediaMeta->data['description'],
                'tags' => is_string($tags) ? explode(', ', $tags) : $tags,
            ]
        ];
    }
}
