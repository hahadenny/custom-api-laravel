<?php

namespace App\Models\Schedule;

use App\Models\Scopes\IsNotExclusionScope;
use App\Models\User;
use App\Services\Schedule\Helpers\ScheduleDatetimeHelper;
use App\Traits\Models\HasExclusions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                                              $id
 * @property bool                                             $is_exclusion        // $this is an exclusion rule
 * @property string|null                                      $start_date          // date
 * @property string|null                                      $start_time          // time
 * @property string|null                                      $end_date            // date
 * @property string|null                                      $end_time            // time
 * @property bool                                             $all_day
 * @property string|null                                      $ends                // "ends on/after/never"
 * @property string|null                                      $repeat_end_date     // date
 * @property string|null                                      $repeat_end_time     // time
 * @property int                                              $max_occurrences     // count
 * @property array                                            $run_on_minutes      // json
 * @property array                                            $run_on_hours        // json
 * @property array                                            $run_on_days         // json
 * @property array                                            $run_on_months       // json
 * @property array                                            $month_day           // json
 * @property string|null                                      $freq
 * @property int                                              $interval
 * @property string                                           $rfc_string          // RRULE string
 * @property string                                           $summary             // readable string
 * @property int                                              $schedule_id
 * @property int                                              $created_by
 * @property \Illuminate\Support\Carbon|null                  $created_at
 * @property \Illuminate\Support\Carbon|null                  $updated_at
 *
 * @property-read \App\Models\User                            $createdBy
 * @property-read \App\Models\Schedule\Schedule               $schedule
 * @property-read \App\Contracts\Models\ScheduleableInterface $scheduleable
 */
class ScheduleRule extends Model
{
    use HasExclusions;

    /** @const array ENDS - valid values for `ends` field */
    public const ENDS = ['ON', 'AFTER', 'NEVER'];

    protected $guarded = [
        'id',
        'schedule_id',
        'rfc_string',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'run_on_days'     => 'array',
        'run_on_months'   => 'array',
        'start_date'      => 'date:Y-m-d',
        'end_date'        => 'date:Y-m-d',
        'repeat_end_date' => 'date:Y-m-d',
        // 'freq'   => Frequency::class,
        'is_exclusion'    => 'boolean',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
        // always only query inclusion rules, unless specified
        static::addGlobalScope(new IsNotExclusionScope());
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedule() : BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Has one Scheduleable entity through Schedule (polymorphic)
     */
    public function scheduleable()
    {
        return $this->schedule->scheduleable();
    }

    /**
     * Get the duration of a rule's "event"/"window"
     *
     * For example, in other contexts this would normally be considered the duration of
     * a single event on a calendar (disregarding any recurrence rules)
     *
     * @throws \Exception
     */
    public function getDuration() : \DateInterval
    {
        $ruleStartDateTime = ScheduleDatetimeHelper::createDateTime($this->start_date->format("Y-m-d"), $this->start_time);
        $ruleEndDateTime = ScheduleDatetimeHelper::createDateTime($this->end_date->format("Y-m-d"), $this->end_time);
        return $ruleStartDateTime->diff($ruleEndDateTime);
    }
}
