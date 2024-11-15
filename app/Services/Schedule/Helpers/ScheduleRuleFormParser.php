<?php

namespace App\Services\Schedule\Helpers;

use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ScheduleRuleFormParser
{
    protected Schedule $schedule;

    /**
     * Format submitted data and set defaults
     */
    public function parse(array $data, Schedule $schedule=null) : array
    {
        $this->schedule ??= Schedule::findOrFail($data['schedule_id']);

        $data = $this->parseRepeatEnd($data);
        $data = $this->parseStartEndDatetimes($data);
        $data = $this->parseFreq($data);

        $data = [
            'id'              => $data['id'] ?? '',
            'schedule_id'     => $data['schedule_id'] ?? null,
            'is_exclusion'    => $data['is_exclusion'] ?? 0,
            'start_date'      => $data['start_date'],
            'start_time'      => $data['start_time'],
            'end_date'        => $data['end_date'] ?? null,
            'end_time'        => $data['end_time'] ?? null,
            'all_day'         => $data['all_day'] ?? 0,
            'freq'            => $data['freq'] ?? 'YEARLY',
            'interval'        => $data['interval'] ?? 1,
            'ends'            => $data['ends'] ?? 'NEVER',
            'max_occurrences' => $data['max_occurrences'] ?? null,
            'repeat_end_date' => $data['repeat_end_date'] ?? null,
            'repeat_end_time' => $data['repeat_end_time'] ?? null,
            'custom_freq'     => $data['custom_freq'] ?? null,
            'run_on_months'   => isset($data['run_on_months'])
                ? array_filter($data['run_on_months'])
                : [],
            'run_on_days'     => isset($data['run_on_days'])
                ? array_filter($data['run_on_days'])
                : [],
            'month_days'      => $data['month_days'] ?? '',
            'schedule'        => $this->schedule,
        ];

        return $data;
    }

    /**
     * Return only the data that matches ScheduleRule attributes
     */
    public function extractRuleAttr(array $form_data) : array
    {
        $attrs = Schema::getColumnListing((new ScheduleRule())->getTable());
        return array_intersect_key(
            $form_data,
            array_fill_keys($attrs, null)
        );
    }

    /**
     * Determine which values to ignore, based on the value of the "Ending:" field on the schedule rule form
     */
    private function parseRepeatEnd(array $form_data) : array
    {
        switch ($form_data['ends']) {
            case 'on':
                // use repeat end date/time
                $form_data['max_occurrences'] = null;
                if(isset($form_data['repeat_end_date']) && empty($form_data['repeat_end_time'])){
                    // default end time should be as soon as the date is hit (00:00:00) for that timezone
                    $form_data['repeat_end_time'] = Carbon::parse($form_data['repeat_end_date'], $this->schedule->timezone)
                                                          ->startOfDay()
                                                          ->format('H:i:s');
                }
                break;
            case 'after':
                // use max_occurrences
                $form_data['repeat_end_date'] = null;
                $form_data['repeat_end_time'] = null;
                break;
            default:
                // never ends
                $form_data['repeat_end_date'] = null;
                $form_data['repeat_end_time'] = null;
                $form_data['max_occurrences'] = null;
        }

        return $form_data;
    }

    /**
     * Set default values for start and end date times ("event" duration) using the schedule timezone
     */
    private function parseStartEndDatetimes(array $data) : array
    {
        $startNow = now($this->schedule->timezone)->startOfDay();
        $endNow = now($this->schedule->timezone)->endOfDay();

        if(empty($data['all_day']) && (empty($data['start_time']) && empty($data['end_time']))){
            // no time specified: rule is "all day"
            $data['all_day'] = 1;
        }

        // default to "now" @ 00:00:00 AM
        $data['start_date'] = empty($data['start_date']) ? $startNow->format('Y-m-d') : $data['start_date'];
        $data['start_time'] = empty($data['start_time']) ? $startNow->format('H:i:s') : $data['start_time'];

        // default to "now" @ 23:59:59 PM
        $data['end_date'] = empty($data['end_date']) ? $endNow->format('Y-m-d') : $data['end_date'];
        $data['end_time'] = empty($data['end_time']) ? $endNow->format('H:i:s') : $data['end_time'];

        return $data;
    }

    /**
     * Set freq to the value of custom_freq if custom is set, or default to "YEARLY"
     */
    private function parseFreq(array $data) : array {
        if(!isset($data['freq'])){
            $data['freq'] = 'YEARLY';
        } elseif($data['freq'] === 'CUSTOM' && isset($data['custom_freq'])){
            $data['freq'] = strtoupper($data['custom_freq']);
        } elseif($data['freq'] === 'NEVER') {
            // this overrides many other values that may have been left over from the UI
            $data = $this->resetRepeatFields($data);
        }
        return $data;
    }

    /**
     * Set all fields related to repeating the rule to neutral values
     */
    private function resetRepeatFields(array $data) : array
    {
        $data['interval'] = 1;
        $data['ends'] = 'NEVER';
        $data['repeat_end_date'] = null;
        $data['repeat_end_time'] = null;
        $data['max_occurrences'] = null;
        $data['run_on_minutes'] = null;
        $data['run_on_hours'] = null;
        $data['run_on_days'] = null;
        $data['run_on_months'] = null;
        $data['month_day'] = null;

        return $data;
    }
}
