<?php

namespace App\Services\Schedule\Helpers;

use App\Enums\Schedule\Day;
use App\Enums\Schedule\Weekday;
use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleRule;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use IntlDateFormatter;
use RRule\RRule;
use RRule\RSet;

/**
 * Convert schedule form data to RFC RRULE string(s) for DB and use with RRule\RSet
 */
class ScheduleRulesetAdapter
{
    public function __construct(
        protected ScheduleRuleFormParser $parser,
    ) {}

    public function createScheduleSummary(Schedule $schedule, array $summaries) : string
    {
        foreach($summaries as $summary) {
            // todo: implement
        }

        // for testing
        return implode(', ', $summaries);
    }

    /**
     * Create RFC-like string of the entire set of ScheduleRules for a Schedule
     * Each rule separated by `//`
     */
    public function createRulesetRfcString(array|SupportCollection|EloquentCollection $rules) : string
    {
        $rfc_strings = is_array($rules)
            ? array_column($rules, 'rfc_string')
            : $rules->pluck('rfc_string')->all();

        return implode('//', $rfc_strings);
    }

    /**
     * Convert an array of ScheduleRules to an RSet, create a human-readable summary of
     * the Schedule's set of rules, and create RFC-like string of the Schedule's
     * entire set of rules. Uses the $schedule's rules if $rules is null.
     * Saves the Schedule changes to DB.
     *
     * @param Schedule            $schedule
     * @param ScheduleRule[]|null $rules
     *
     * @return RSet
     */
    public function createRecurrenceDataForSchedule(Schedule $schedule, array $rules = null) : RSet
    {
        // ray("called createRecurrenceDataForSchedule()");
        $rules ??= $schedule->allRules->all();
        $set = new RSet();
        $summaries = [];

        foreach($rules as $rule) {
            // todo: handle/check for specific RDATE/EXDATE (if that is not in rfc_string)
            $rrule = new RRule($rule->rfc_string);

            $summaries [] = $this->singleReadableRfcSummary($rule, $schedule);

            $set = $this->appendToRSet($set, $rrule, $rule->is_exclusion);
        };

        $schedule->summary = $this->createScheduleSummary($schedule, $summaries);
        $schedule->rfc_string = $this->createRulesetRfcString($rules);
        $schedule->save();

        return $schedule->setRSet($set);
    }

    /**
     * Add a recurrence rule or exclusion rule to an RSet
     */
    private function appendToRSet(RSet $ruleSet, RRule $rrule, bool $is_exclusion = false) : RSet
    {
        if($is_exclusion) {
            return $ruleSet->addExRule($rrule);
        }

        return $ruleSet->addRRule($rrule);
    }

    /**
     * Create RFC-like syntax (mainly for use by \RRule\RSet).
     *
     * The string may be a multiple line string (DTSTART and RRULE),
     * a single line string (only RRULE), or just the RRULE property value.
     * -- Note: DTSTART and RRULE must be separated by \n (LF), or \r\n (CRLF).
     *
     * @see https://github.com/rlanvin/php-rrule/wiki/RRule#creation
     * @see https://github.com/rlanvin/php-rrule/wiki/RSet
     *
     * @param array $data - form ruleset data
     *
     * @throws \Exception
     * @see https://www.php.net/manual/en/timezones.php
     */
    public function convertFormDataToRfc(array $data) : string
    {
        // todo: build the string by passing the parts to RRule\RRule?
        $rfc_string = '';

        // DTSTART // TZID
        $rfc_string .= $RFC_dtstart = $this->determineDtStart($data['start_date'], $data['start_time'],);

        // -- RULES --
        // RRULE --> FREQ; INTERVAL; UNTIL; COUNT; BY____; BYSETPOS;
        // EXRULE --> FREQ; INTERVAL; UNTIL; COUNT; BY____; BYSETPOS;

        // determine label: RRULE or EXRULE
        $RFC_label = $data['is_exclusion'] ? 'EXRULE:' : 'RRULE:';

        $rfc_string = $this->addRfcLine($rfc_string, $RFC_label);

        // determine FREQ
        $rfc_string .= $RFC_freq = $this->rfcFreq($data);

        // determine INTERVAL
        if( !empty($data['interval'])) {
            $rfc_string .= $RFC_interval = $this->rfcInterval($data['interval']);
        }/* // END no longer used
        elseif($data['freq'] === 'END' && $data['duration'] !== null) {
            // When looping (repeat@end), use the loop's total duration as the interval
            $rfc_string .= $RFC_interval = $this->rfcInterval($data['interval']);
        }*/


        // determine UNTIL --or-- COUNT
        $rfc_string .= $RFC_until_or_count = $this->rfcRuleEnd($data);

        // determine BY____ (multiple allowed)
        $rfc_string .= $RFC_byrules = $this->rfcByRules($data);

        // todo: specific dates(?)
        // RDATE
        // EXDATE

        return trim($rfc_string, ';');
    }

    /**
     * Return RFC-like string of more specific things within a
     * frequency period to apply the recurrence to
     * i.e., months, days of the week, days of the year, Nth occurrences, etc
     */
    private function rfcByRules(array $data) : string
    {
        $byRules = '';
        // bymonth
        $byRules .= $RFC_bymonth = $this->rfcByMonth($data['run_on_months']);
        // byday/byweekday
        $byRules .= $RFC_byday = $this->rfcByDay($data['run_on_days']);
        // todo: finish bysetpos
        $byRules .= $this->rfcBySetPos($data);

        // todo: bymonthday
        // todo: byweekno
        // todo: byyearday
        // todo: byhour
        // todo: byminute
        /*
         * The second(s) to apply the recurrence to, from 0 to 60.
         * It can be a single value, or a comma-separated list or an array.
         * Warning: leap second (i.e. second 60) support is not fully tested.
         */
        // todo: bysecond

        return $byRules;
    }

    /**
     * Return an RFC-like representation of the Nth occurrence(s) within
     * the valid occurrences inside a frequency period.
     * Negative values mean that the count starts from the set.
     *
     * For example, a bysetpos of -1 if combined with a MONTHLY frequency,
     * and a byday of 'MO,TU,WE,TH FR', will result in the
     * last work day of every month.
     */
    private function rfcBySetPos(array $data) : string
    {
        if( !empty($data['month_days'])) {
            $days = $this->rfcBySetPosInterval($data['month_days']);
        } elseif( !empty($data['weekdays'])) {
            $days = $this->rfcBySetPosInterval($data['weekdays']);
        }

        return isset($days) ? "BYSETPOS=$days;" : '';
    }

    /**
     * @param string|string[] $data
     */
    private function rfcBySetPosInterval(string|array $data) : string|int
    {
        return match (true) {
            // last day of the month
            $data == 'last'  => -1,
            // first day of the month
            $data == 'first' => 1,
            // month days were entered directly
            is_array($data)  => implode(',', $data),
            default          => $data,
        };
    }

    /**
     * Return an RFC-like representation of the day(s) of the week to apply
     * the recurrence to from the following: MO, TU, WE, TH, FR, SA, SU.
     *
     * -- TODO: implement number prefix:
     * Each day can be preceded by a number, indicating a specific
     * occurrence within the interval.
     * For example: 1MO (the first Monday of the interval), 3MO (the third Monday),
     * -1MO (the last Monday), and so on.
     *
     * @param string[]|int[] $days
     */
    private function rfcByDay(array $days) : string
    {
        $days = array_filter($days);

        $rfc_days = [];
        foreach($days as $i => $day) {
            if(strtoupper($day) === 'WEEKDAYS') {
                // replace WEEKDAYS with every RFC weekday
                $rfc_days = array_merge($rfc_days, Weekday::names());
                continue;
            }
            if(strtoupper($day) === 'EVERYDAY') {
                // replace `EVERYDAY` with all RFC day codes
                $rfc_days = array_merge($rfc_days, ['SU', ...Weekday::names(), 'SA']);
                continue;
            }

            // convert full weekday name to RFC value
            $rfc_days [] = Day::from($day)->name;
        }

        if(empty(array_filter($rfc_days))) {
            return '';
        }

        return "BYDAY=" . implode(',', $rfc_days) . ";";
    }

    /**
     * Return an RFC-like representation of the month(s) to apply
     * the recurrence to, from 1 (January) to 12 (December)
     * (a single value, a comma-separated list or an array)
     *
     * @param string[]|int[] $months
     *
     * @return string|void
     */
    private function rfcByMonth(array $months)
    {
        $months = array_filter($months);

        if(empty($months)) {
            return '';
        }

        if(is_numeric(reset($months))) {
            return "BYMONTH=" . implode(',', $months) . ";";
        }

        // determine whether month names are abbreviated or full
        $format = (mb_strlen(reset($months)) > 3) ? 'M' : 'F';

        $months = array_map(function($month) use ($format) {
            return DateTimeImmutable::createFromFormat($format, $month)->format('n');
        }, $months);

        return "BYMONTH=" . implode(',', $months) . ";";
    }

    /**
     * Determine whether COUNT or UNTIL should be used, and
     * return an RFC-like string representation
     *
     * @throws \Exception
     */
    private function rfcRuleEnd(array $data, string $fromFormat = 'Y-m-d H:i:s') : string
    {
        // Log::debug(" -- rfcRuleEnd() -- data: ".json_encode($data));

        if($data['freq'] === 'NEVER') {
            return '';
        }

        if(empty($data['max_occurrences'])) {
            if(empty($data['repeat_end_date'])) {
                // never ends
                return '';
            }

            // ray('end date NOT empty')->orange();

            // looping mode --> calculate datetime based on page duration
            // Log::debug('(id: '.$data["id"].', schedule id: '.$data["schedule_id"].') freq: '.$data['freq']);
            // Log::debug('(id: '.$data["id"].', schedule id: '.$data["schedule_id"].') duration: '.$data['duration']);

            /*// freq = END no longer a thing
            if($data['freq'] === 'END' && $data['duration'] !== null) {
                $startDateTime = ScheduleDatetimeHelper::createDateTime($data['start_date'], $data['start_time'], $fromFormat);
                // Log::debug('start datetime: '.$startDateTime->format("Y-m-d H:i:s"));

                // use duration instead of submitted end date and time
                $datetime = ScheduleDatetimeHelper::calculateDurationEnd($startDateTime, $data['duration']);
            } else {*/
                $datetime = ScheduleDatetimeHelper::createDateTime($data['repeat_end_date'], $data['repeat_end_time'], $fromFormat);
            // }

            // ray('result: '.$datetime->format('Y-m-d H:i:s'))->green();
            // ray('UNTIL: '.$datetime->format('Ymd\THis') . "Z;")->green();

            /**
             * @see \RRule\RRule::parseRRule
             * > if the \"DTSTART\" property is specified as a date with UTC time or
             * > a date with local time and time zone reference, then the UNTIL
             * > rule part MUST be specified as a date with UTC time
             */
            return "UNTIL=" . $datetime->format('Ymd\THis') . "Z;";
        }

        return "COUNT=" . $data['max_occurrences'] . ";";
    }

    /**
     * Return an interval value that the RFC RRULE can use
     */
    private function rfcInterval(int $interval) : string
    {
        return "INTERVAL=$interval;";
    }

    /**
     * Return a frequency value that the RFC RRULE can use
     * or a default of YEARLY
     *
     * @param array $data       -- `$data` also contains duration [in seconds, of self (page),
     *                          or the sum the duration of the children (playlist, layer)]
     */
    private function rfcFreq(array $data) : string
    {
        return "FREQ=" . $this->determineFreq($data) . ";";
    }

    /**
     * Return a string representing a frequency that the RFC RRULE can use
     * or a default of YEARLY
     *
     * @param array $data       -- `$data` also contains duration [in seconds, of self (page),
     *                          or the sum the duration of the children (playlist, layer)]
     */
    protected function determineFreq(array $data) : string
    {
        $freq = strtoupper($data['freq']);

        if(in_array($freq, array_keys(RRule::FREQUENCIES))) {
            // frequency is not customized
            return $freq;
        }

        // determine custom frequency
        // NOTE: `END` is no longer relevant --
        /* if($freq === 'END') {
            /**
             *  When the repeat frequency is set to loop, FREQ should be determined
             *  by the duration of the pages in the loop (can be up to `HOURLY`)
             *
            return "SECONDLY";
        } //*/

        // default
        return "YEARLY";
    }

    /**
     * @param string $date - Y-m-d format
     * @param string $time - H:i:s (24hr) format
     * @param string $format
     *
     * @return string
     * @throws \Exception
     * @see https://www.php.net/manual/en/timezones.php
     */
    protected function determineDtStart(
        string $date,
        string $time,
        string $format = 'Ymd\THis'
    ) : string
    {

        $datetime = ScheduleDatetimeHelper::createDateTime($date, $time)->format($format);

        /*if ($timezone == 'UTC') {
            // don't include TZID for UTC timezone
            return "DTSTART;".$datetime."Z";
        }*/

        return "DTSTART;TZID=" . Schedule::DEFAULT_TIMEZONE . ":" . trim($datetime);
    }

    /**
     * Ensure RFC string has necessary linebreaks
     */
    private function addRfcLine(string $rfc, string $line, string $linebreak = "\n") : string
    {
        return $rfc . $linebreak . $line;
    }

    /**
     * Update the rule's human-readable text
     */
    public function updateRuleSummary(ScheduleRule $rule) : ScheduleRule
    {
        $rule->summary = $this->singleReadableRfcSummary($rule);
        // Log::debug('Rule summary: '.$rule->summary);
        return $rule;
    }

    /**
     * Convert rules form data to RFC-like string and set the rule's rfc_string
     *
     * @throws \Exception
     */
    public function updateRuleRfc(ScheduleRule $rule, array $rule_data) : ScheduleRule
    {
        $rfc_string = $this->convertFormDataToRfc($rule_data);
        // Log::debug('RFC string: '.$rfc_string);
        $rule->rfc_string = $rfc_string;

        return $rule;
    }

    /**
     * Create the schedule's RFC string summarizing the rules, and
     * initialize a related \RRule\RSet as a property of the Schedule
     */
    public function updateScheduleRecurrenceData(Schedule $schedule, array $rules = null) : Schedule
    {
        // hydrate schedule with updated rules
        $schedule->isDirty() ? $schedule->load('rules') : $schedule->loadMissing('rules');

        // create human-readable summary of entire set of rules
        // create RFC-like string of entire set of rules
        // make \RRule\RSet from rules, so we can use it to calculate occurrences
        $this->createRecurrenceDataForSchedule($schedule, $rules);

        return $schedule;
    }

    //  @link https://github.com/rlanvin/php-rrule/wiki/RRule#humanreadablearray-opt
    private function singleReadableRfcSummary(ScheduleRule $rule, Schedule $schedule = null) : string
    {
        $schedule ??= $rule->schedule;
        $rrule = new RRule($rule->rfc_string);
        $timezone = $schedule->timezone instanceof DateTimeZone ? $schedule->timezone : new DateTimeZone($schedule->timezone);

        $humanReadable = $rrule->humanReadable([
             'explicit_infinite' => false,
             'date_format'       => IntlDateFormatter::SHORT,
             'time_format'       => IntlDateFormatter::SHORT,
             // convert according to schedule timezone so that the rfc summary reads correctly
             'date_formatter'    => function(\DateTimeInterface $datetime) use ($timezone) {
                 $format = "n/j/y g:i:s A";
                 $datetime = $datetime->setTimezone($timezone);

                 return $datetime->format("g:i:s A") === '12:00:00 AM'
                     ? $datetime->format("n/j/y")
                     : $datetime->format($format);
             },
         ]);

        if($rule->is_exclusion){
            $humanReadable = 'except ' . $humanReadable;
        }

        return $humanReadable;
    }
}
