<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SocketServerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /** @var \App\Models\SocketServer $server */
        $server = $this->resource;

        return [
            'id' => $server->id,
            'url' => $server->url,
            'cluster' => $server->cluster,
        ];
    }
}
