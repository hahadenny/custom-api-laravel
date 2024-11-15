<?php

namespace App\Services\Schedule\Helpers;

use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleRule;
use App\Models\User;

/**
 *
 */
class ScheduleRuleProcessor
{
    public function __construct(
        protected ScheduleRuleFormParser $parser,
        protected ScheduleRulesetAdapter $adapter
    ) {}

    /**
     * @throws \Exception
     * Loop the submitted rules form data for processing
     */
    public function processRules(array $form_rules, Schedule $schedule, User $creator) : array
    {
        // ray($form_rules);
        // ray($schedule);
        $rules = [];
        foreach($form_rules as $rule_data) {
            $rules [] = $this->processRule($rule_data, $schedule, $creator);
        }
        return $rules;
    }

    /**
     * Convert the form rule data into a ScheduleRule and save it to the DB
     *
     * @throws \Exception
     */
    public function processRule(
        array        $rule_form_data,
        Schedule     $schedule,
        User         $creator,
        ScheduleRule $rule = null
    ) : ScheduleRule
    {

        // todo: check for conflicts with existing rules?

        $rule_form_data = $this->standardizeDatetimes($rule_form_data, $schedule);
        $rule_attr_data = $this->parser->extractRuleAttr($rule_form_data);

        if( !empty($rule_form_data['id'])) {
            // existing rule
            $rule ??= ScheduleRule::find($rule_attr_data['id']);
            $rule->fill($rule_attr_data);
        } else {
            // new rule
            $rule = new ScheduleRule($rule_attr_data);
            $rule->createdBy()->associate($creator);
            $rule->schedule()->associate($schedule);
        }

        // -- (re)calculate & update the rule's RFC string
        $rule = $this->adapter->updateRuleRfc($rule, $rule_form_data);
        $rule = $this->adapter->updateRuleSummary($rule);

        $rule->save();

        return $rule;
    }

    /**
     * Convert validated form datetimes from the schedule's timezone to UTC
     *
     * @throws \Exception
     */
    private function standardizeDatetimes(array $rule_form_data, Schedule $schedule) : array
    {
        $start_datetime = ScheduleDatetimeHelper::standardizeDateTimezone(
            $rule_form_data['start_date'],
            $rule_form_data['start_time'],
            $schedule->timezone,
        );
        $end_datetime = ScheduleDatetimeHelper::standardizeEndDateTimezone(
            $rule_form_data['end_date'],
            $rule_form_data['end_time'],
            $schedule->timezone,
        );

        $rule_form_data['start_date'] = $start_datetime->format('Y-m-d');
        $rule_form_data['start_time'] = $start_datetime->format('H:i:s');
        $rule_form_data['end_date'] = $end_datetime->format('Y-m-d');
        $rule_form_data['end_time'] = $end_datetime->format('H:i:s');

        if(isset($rule_form_data['repeat_end_date'])){
            $repeat_end_datetime = ScheduleDatetimeHelper::standardizeEndDateTimezone(
                $rule_form_data['repeat_end_date'],
                $rule_form_data['repeat_end_time'],
                $schedule->timezone,
            );
            $rule_form_data['repeat_end_date'] = $repeat_end_datetime->format('Y-m-d');
            $rule_form_data['repeat_end_time'] = $repeat_end_datetime->format('H:i:s');
        }

        return $rule_form_data;
    }
}
