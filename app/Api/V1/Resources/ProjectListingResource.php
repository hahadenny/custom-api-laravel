<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /** @var \App\Models\Project $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'sort_order' => $resource->sort_order,
            'created_at' => $resource->created_at,
            'updated_at' => $resource->updated_at,
            'workspace' => $resource->getWorkspace(),
            'is_active' => $resource->getUserProject()?->is_active ?? false,
            'createdBy' => $resource->createdBy,
        ];
    }
}
