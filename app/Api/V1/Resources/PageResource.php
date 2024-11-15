<?php

namespace App\Api\V1\Resources;

use App\Models\Channel;
use App\Services\Schedule\Helpers\ScheduleDatetimeHelper;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PageResource extends JsonResource
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
        /** @var \App\Models\Page $page */
        $page = $this->resource;
        $reference = null;

        if (! is_null($page->original_id) && ! is_null($page->original)) {
            $reference = $page;
            $page = $page->original;
        }

        if ($page->channel) {
            $page->channel->loadMissing(['parent:id,name,type,is_preview']);
        }
        if ($page->channelEntity instanceof Channel) {
            $page->channelEntity->loadMissing(['parent:id,name,type,is_preview']);
        }

        return [
            'id' => is_null($reference) ? $page->id : $reference->id,
            'playlist_id' => $page->playlist_id,
            'page_group_id' => $page->page_group_id,
            'name' => is_null($reference) ? $page->name : $reference->name,
            'page_number' => $page->page_number,
            'description' => $page->description,
            'is_live' => is_null($reference) ? $page->is_live : $reference->is_live,
            'data' => $page->data,
            'created_at' => is_null($reference) ? $page->created_at : $reference->created_at,
            'updated_at' => is_null($reference) ? $page->updated_at : $reference->updated_at,
            'created_by' => is_null($reference) ? $page->createdBy : $reference->createdBy,
            'template_id' => $page->template_id,
            'template' => $page->template,
            'channel' => $page->channel,
            'channel_id' => $page->channel_id,
            'channel_entity_type' => $page->channel_entity_type,
            'channel_entity_id' => $page->channel_entity_id,
            'channel_entity' => $page->channelEntity,
            'has_media' => $page->has_media,
            'sort_order' => is_null($reference) ? $page->sort_order : $reference->sort_order,
            'edited_fields' => $page->edited_fields,
            'preview_url' => $page->preview_url ?
                Storage::disk(config('media-library.disk_name'))->url($page->preview_url) :
                null,
            'color' => $page->color,
            'subchannel' => $page->subchannel,

            // format duration field to H:i:s
            // If no duration is set, the default duration from the template is used
            //  (This is done by the front end code, or by using Page::getDuration())
            'duration' => !empty($page->duration)
                ? ScheduleDatetimeHelper::formatDurationOutput($page->duration)
                : null
        ];
    }
}
