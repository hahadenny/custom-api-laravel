<?php

namespace App\Traits\Models\Schedule;

use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleListing;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For models that implement App\Contracts\Models\ScheduleableInterface,
 * meaning they are related to a Schedule via ScheduleListing
 *
 * @property-read \App\Models\Schedule\Schedule $schedule
 * @property-read \Illuminate\Database\Eloquent\Collection<ScheduleListing> $scheduleListingPivots
 */
trait IsScheduleable
{
    public function schedule()
    {
        return $this->hasManyThrough(
            Schedule::class,
            ScheduleListing::class,
            'schedule_id',
            'schedule_listing_id',
        );
    }

    public function scheduleListingPivots() : MorphMany
    {
        return $this->morphMany(ScheduleListing::class, 'scheduleable');
    }

    public function getDuration() : int
    {
        // avoid double counting now that duration is in ScheduleListing
        return 0;
    }
}
