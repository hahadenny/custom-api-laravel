<?php

namespace App\Services\Schedule;

use App\Enums\Schedule\ScheduleOrigin;
use App\Models\Schedule\ScheduleListing;
use App\Models\User;
use App\Services\Schedule\Helpers\ScheduleFactory;
use App\Services\Schedule\Helpers\ScheduleRuleProcessor;
use App\Services\Schedule\Helpers\ScheduleRulesetAdapter;

class ScheduleService
{
    public function __construct(
        protected ScheduleRulesetAdapter $rulesetAdapter,
        protected ScheduleFactory $factory,
        protected ScheduleRuleProcessor $ruleProcessor,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function store(User $authUser, array $data)
    {
        /**
         * @var string $timezone
         *      ex) America/New_York, UTC, Europe/London, etc
         * @see https://www.php.net/manual/en/timezones.php
         */
        $timezone   = $data['timezone'] ?? null;
        $form_rules = $data['rulesets'] ?? [];
        $scheduleListing = ScheduleListing::findOrFail($data['schedule_listing_id']);

        $schedule = $this->factory->make(
            $scheduleListing,
            $authUser,
            ScheduleOrigin::Scheduled,
            $timezone,
        );

        $scheduleRules = $this->ruleProcessor->processRules($form_rules, $schedule, $authUser);

        return $this->rulesetAdapter->updateScheduleRecurrenceData($schedule, $scheduleRules);
    }
}
