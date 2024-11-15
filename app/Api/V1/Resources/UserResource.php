<?php

namespace App\Api\V1\Resources;

use App\Models\User;

class UserResource extends UserListingResource
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
        $data['permissions'] = $user->permissions->pluck('name');
        return $data;
    }
}
