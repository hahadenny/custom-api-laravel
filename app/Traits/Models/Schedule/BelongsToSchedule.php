<?php

namespace App\Traits\Models\Schedule;

use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleRule;

/**
 * Model with a belongsTo relation to a Schedule
 *
 * @property int|null                                          $schedule_id
 * @property \App\Models\Schedule\Schedule                     $schedule
 * @property-read \Illuminate\Support\Collection<ScheduleRule> $rules
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait BelongsToSchedule
{
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function rules()
    {
        return $this->hasManyThrough(
            ScheduleRule::class,
            Schedule::class,
            'scheduleable_id',
            'schedule_id',
        );
    }
}
