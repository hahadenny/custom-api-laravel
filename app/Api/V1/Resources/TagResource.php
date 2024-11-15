<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     * @throws \Exception
     */
    public function toArray($request)
    {
        $tag = $this->resource;
        return [
            'id' => $tag->id,
            'name' => $tag->name,
        ];
    }
}
