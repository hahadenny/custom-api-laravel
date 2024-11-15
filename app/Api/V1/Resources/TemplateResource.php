<?php

namespace App\Api\V1\Resources;

use App\Models\Schedule\ScheduleListing;
use App\Services\Schedule\Helpers\ScheduleDatetimeHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
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
        $array = parent::toArray($request);
        // format duration field to H:i:s
        $array['default_duration'] = !empty($array['default_duration'])
            ? ScheduleDatetimeHelper::formatDurationOutput($array['default_duration'])
            : ScheduleDatetimeHelper::formatDurationOutput(ScheduleListing::DEFAULT_DURATION);
        $array['tags'] = TagResource::collection($this->whenLoaded('tags'));
        return $array;
    }
}
