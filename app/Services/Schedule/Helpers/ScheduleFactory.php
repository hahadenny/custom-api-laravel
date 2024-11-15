<?php

namespace App\Services\Schedule\Helpers;

use App\Enums\Schedule\ScheduleOrigin;
use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleListing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScheduleFactory
{
    /**
     * Instantiate a new Schedule with timezone and associate the
     * related User, Scheduleable, and ScheduleableParent
     *
     * @param int|ScheduleListing       $scheduleListing
     * @param User                      $creator
     * @param ScheduleOrigin            $origin
     * @param string|\DateTimeZone|null $timezone
     *
     * @return Schedule
     */
    public function make(
        int|ScheduleListing $scheduleListing,
        User $creator,
        ScheduleOrigin $origin = ScheduleOrigin::Scheduled,
        string|\DateTimeZone $timezone = null,
    ) : Schedule {

        $scheduleListing = is_int($scheduleListing) ? ScheduleListing::findOrFail($scheduleListing) : $scheduleListing;

        $timezone = $timezone instanceof \DateTimeZone ? $timezone->getName() : $timezone;
        // no range or summary will be sent on initial creation
        $schedule = new Schedule([
            'timezone' => $timezone ?? null,
            'origin' => $origin,
        ]);

        DB::transaction(function () use ($schedule, $creator,$scheduleListing) {
            $schedule->createdBy()->associate($creator);
            $schedule->save();
            $scheduleListing->schedule()->associate($schedule);
            $scheduleListing->save();
        });

        return $schedule;
    }
}
