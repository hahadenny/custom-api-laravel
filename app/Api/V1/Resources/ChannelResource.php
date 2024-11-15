<?php

namespace App\Api\V1\Resources;

use App\Models\ChannelGroup;
use Illuminate\Http\Resources\Json\JsonResource;

class ChannelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /** @var \App\Models\Channel $channel */
        $channel = $this->resource;
        $data = parent::toArray($request);
        if ($channel instanceof ChannelGroup || is_array($channel)) {
            $data['components'] = ChannelResource::collection(
                is_array($channel) ? $channel['components'] : $channel->components
            );
            return $data;
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        if ($user && !$user->isAdmin()) {
            $user->loadMissing(['channelGroups:id,name,parent_id', 'channelGroups.channels:id,name,channel_group_id']);
        }

        $data['has_access'] = $this->when($user, function() use ($user, $channel) {
            return $user->hasChannelAccess($channel);
        }, false);
        return $data;
    }
}
