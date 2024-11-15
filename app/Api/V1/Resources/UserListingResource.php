<?php

namespace App\Api\V1\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /* @var $user User */
        $user = $this->resource;
        $data = parent::toArray($request);
        $data['channel_group_ids'] = $user->channelGroups->pluck('id');
        return $data;
    }
}
