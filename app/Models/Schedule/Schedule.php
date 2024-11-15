<?php

namespace App\Models\Schedule;

use App\Enums\Schedule\ScheduleOrigin;
use App\Models\User;
use App\Services\Schedule\Helpers\ScheduleDatetimeHelper;
use App\Services\Schedule\Helpers\ScheduleRulesetAdapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RRule\RSet;

/**
 * @property int                                       $id
 * @property string|\App\Enums\Schedule\ScheduleOrigin $origin
 * @property string|null                               $summary              // human readable rules
 * @property string                                    $rfc_string           // rules' RRULE strings concatenated with `//`
 * @property string                                    $timezone             // timezone
 * @property int                                       $created_by
 * @property \Illuminate\Support\Carbon|null           $created_at
 * @property \Illuminate\Support\Carbon|null           $updated_at
 *
 * @property-read \App\Models\User                     $createdBy
 * @property-read \App\Models\User                     $creator
 *
 * @method \Illuminate\Database\Eloquent\Builder whereHasExclusionRules()
 */
class Schedule extends Model
{
    /**
     * Use this as the timezone when calculating RFC-like strings
     *
     * @const string
     */
    public const DEFAULT_TIMEZONE = 'UTC';

    protected $guarded = [
        'id',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'origin' => ScheduleOrigin::class,
    ];

    protected $with = ['rules'];

    /**
     * Dynamically hold the set of rules for the schedule
     */
    protected RSet $RSet;

    /**
     * Access $this->RSet or generate it if missing and adapter is available
     *
     * @param ScheduleRulesetAdapter|null $adapter
     *
     * @return RSet|null
     */
    public function getRSet(ScheduleRulesetAdapter $adapter = null) : ?RSet
    {
        if ( !empty($adapter) && empty($this->RSet) && $this->rules()->count() > 0) {
            $set = $adapter->createRecurrenceDataForSchedule($this, $this->rules->all());
            $this->setRSet($set);
        }
        return $this->RSet ?? null;
    }

    public function setRSet(RSet $set) : RSet
    {
        $this->RSet = $set;
        return $this->RSet;
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creator()
    {
        return $this->createdBy();
    }

    public function rules()
    {
        return $this->hasMany(ScheduleRule::class);
    }

    public function allRules()
    {
        return $this->rules()->withExclusions();
    }

    public function listings()
    {
        return $this->hasMany(ScheduleListing::class);
    }

    /**
     * Get the given collection of rules with their date times converted from
     * a given timezone to this schedule's timezone.
     * Defaults to $this Schedule's (non-exclusion) rules and converting from UTC
     *
     * @throws \Exception
     */
    public function getConvertedRules(
        string|\DateTimeZone $fromTimezone = Schedule::DEFAULT_TIMEZONE,
        Collection $rules=null
    ) : Collection
    {
        $rules ??= $this->rules;
        return $rules->map(function($rule) use ($fromTimezone) {
            $standardized = ScheduleDatetimeHelper::standardizeDateTimezone(
                $rule->start_date->format("Y-m-d"),
                $rule->start_time,
                $fromTimezone,
                $this->timezone,
            );
            $rule->start_date = $standardized->format("Y-m-d");
            $rule->start_time = $standardized->format("H:i:s");

            if(isset($rule->end_date)){
                // we don't need to use standardizeEndDateTimezone() because end_time should already be correct
                $end_standardized = ScheduleDatetimeHelper::standardizeDateTimezone(
                    $rule->end_date->format("Y-m-d"),
                    $rule->end_time,
                    $fromTimezone,
                    $this->timezone,
                );
                $rule->end_date = $end_standardized->format("Y-m-d");
                $rule->end_time = $end_standardized->format("H:i:s");
            }

            if(isset($rule->repeat_end_date)){
                // we don't need to use standardizeEndDateTimezone() because repeat_end_time should already be correct
                $end_standardized = ScheduleDatetimeHelper::standardizeDateTimezone(
                    $rule->repeat_end_date->format("Y-m-d"),
                    $rule->repeat_end_time,
                    $fromTimezone,
                    $this->timezone,
                );
                $rule->repeat_end_date = $end_standardized->format("Y-m-d");
                $rule->repeat_end_time = $end_standardized->format("H:i:s");
            }

            return $rule;
        });
    }

    /**
     * Get all of the Schedule's related rules with their date times converted from
     * a given timezone to this schedule's timezone.
     *
     * @throws \Exception
     */
    public function getAllConvertedRules(string|\DateTimeZone $fromTimezone = Schedule::DEFAULT_TIMEZONE) : Collection
    {
        return $this->getConvertedRules($fromTimezone, $this->allRules);
    }

    /******************************************
     * SCOPES
     ************/

    /**
     * Scope for querying schedules that have exclusion rules
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeWhereHasExclusionRules(Builder $query)
    {
        return $query->whereHas('rules', function (Builder $query) {
            $query->where('is_exclusion', '=', true);
        });
    }
}
