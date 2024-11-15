<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChannelPermissionableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /* @var $group \App\Models\ChannelPermissionable */
        $group = $this->resource;
        return [
            'id' => $group->id,
            'channel_id' => $group->channel_id,
            'permissions' => $group->permissions->pluck('name')
        ];
    }
}
