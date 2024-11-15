<?php

namespace App\Api\V1\Resources\Schedule;

use App\Services\Schedule\Helpers\ScheduleDatetimeHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleListingResource extends JsonResource
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
        return ScheduleDatetimeHelper::recursivelyFormatDuration(parent::toArray($request));
    }
}
