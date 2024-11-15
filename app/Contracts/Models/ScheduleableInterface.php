<?php

namespace App\Contracts\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Entities that can be scheduled/have schedule rules, but may not necessarily be able to play out.
 * In other words, they have relationships with ScheduleListing (and therefore Schedule/ScheduleRule).
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Schedule\ScheduleRule>    $rules
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Schedule\ScheduleListing> $scheduleListingPivots
 * @property-read \App\Models\Schedule\Schedule                                                  $schedule
 * @mixin \Illuminate\Database\Eloquent\Model
 */
interface ScheduleableInterface
{
    public function schedule();

    public function scheduleListingPivots() : MorphMany;

    public function getDuration() : int;
}
