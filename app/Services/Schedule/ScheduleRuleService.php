<?php

namespace App\Services\Schedule;

use App\Contracts\Models\ScheduleableInterface;
use App\Enums\Schedule\ScheduleOrigin;
use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleRule;
use App\Models\User;
use App\Services\Schedule\Helpers\ScheduleRuleProcessor;
use App\Services\Schedule\Helpers\ScheduleRulesetAdapter;
use Illuminate\Support\Facades\DB;

/**
 * todo: update
 */
class ScheduleRuleService
{
    public function __construct(
        protected ScheduleRulesetAdapter $adapter,
        protected ScheduleRuleProcessor $processor,
    ) {
    }

    // --------------------------------------------------------------------

    /**
     * Get rrules for the specified scheduleable instance
     *
     * @param ScheduleableInterface $scheduleable
     *
     * @return mixed
     */
    public function listing(ScheduleableInterface $scheduleable)
    {
        return ScheduleRule::whereScheduleables($scheduleable->id, $scheduleable::class)->get();
    }

    /**
     * @throws \Exception
     */
    public function store(User $authUser, array $data, Schedule $schedule)
    {
        // make sure origin is corrected if it was generated before
        $schedule->origin = ScheduleOrigin::Scheduled;
        $schedule->save();
        $scheduleRule = $this->processor->processRule($data, $schedule, $authUser);
        // hydrate schedule instance with its new rule(s)
        $schedule->load('rules');

        // needed outside of $occurrenceFactory->generate() because this updates the Schedule RFC string and summary
        $this->adapter->createRecurrenceDataForSchedule($schedule);

        return $scheduleRule->load('schedule.rules');
    }

    /**
     * @throws \Exception
     */
    public function update(?User $authUser, array $data, Schedule $schedule, ScheduleRule $rule)
    {
        // make sure origin is corrected if it was generated before
        $schedule->origin = ScheduleOrigin::Scheduled;
        $schedule->save();

        $rule = $this->processor->processRule($data, $schedule, $authUser, $rule);
        // hydrate schedule instance with its updated rule(s)
        $schedule->load('rules');
        // needed outside of $occurrenceFactory->generate() because this updates the Schedule RFC string and summary
        $this->adapter->createRecurrenceDataForSchedule($schedule);

        return $rule->load('schedule.rules');
    }

    /**
     * Delete the specified rule and fire deleted model event
     *
     * @param ScheduleRule $rule
     * @param Schedule     $schedule
     *
     * @return void
     */
    public function delete(ScheduleRule $rule, Schedule $schedule) : void
    {
        DB::transaction(function () use ($rule, $schedule) {
            $rule->delete();
            // hydrate schedule instance with its updated rule(s)
            $schedule->load('rules');
            // needed outside of $occurrenceFactory->generate() because this updates the Schedule RFC string and summary
            $this->adapter->createRecurrenceDataForSchedule($schedule);
        });
    }
}
