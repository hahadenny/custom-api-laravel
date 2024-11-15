<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserGroupPermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /* @var $group \App\Models\UserGroup */
        $group = $this->resource;
        return [
            'id' => $group->id,
            'name' => $group->name,
            'channel_permissionables' => ChannelPermissionableResource::collection($group->channelPermissionables)
        ];
    }
}
