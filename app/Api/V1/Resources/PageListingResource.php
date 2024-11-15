<?php

namespace App\Api\V1\Resources;

use App\Services\Schedule\Helpers\ScheduleDatetimeHelper;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;


class PageListingResource extends JsonResource
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

        $data = $page->toArray();
        $data['preview_url'] = $page->preview_url ?
            Storage::disk(config('media-library.disk_name'))->url($page->preview_url) :
            null;

        // format duration field from seconds to H:i:s
        // If no duration is set, the default duration from the template is used
        //  (This is done by the front end code, or by using Page::getDuration())
        $data = ScheduleDatetimeHelper::recursivelyFormatDuration($data, 'components');

        if (! is_null($reference)) {
            $data['id'] = $reference->id;
            $data['original_id'] = $reference->original_id;
            $data['name'] = $reference->name;
            $data['is_live'] = $reference->is_live;
            $data['sort_order'] = $reference->sort_order;
            $data['created_at'] = $reference->created_at;
            $data['updated_at'] = $reference->updated_at;
        }

        return $data;
    }
}
