<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
        $user = $request->user();

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'sort_order' => $resource->sort_order,
            'created_at' => $resource->created_at,
            'updated_at' => $resource->updated_at,
            'workspace' => $resource->getWorkspace(),
            'user_project' => $this->when($user, function() use ($user, $resource) {
                return $resource->userProjects()->where(['user_id' => $user->id])->first();
            }),
            'createdBy' => $resource->createdBy,
        ];
    }
}
