<?php

namespace App\Models\Schedule;

use App\Models\Channel;
use App\Models\Schedule\States\Next;
use App\Models\Schedule\States\Playing;
use App\Models\Schedule\States\PlayoutState;
use App\Traits\Models\Schedule\HasPlayoutState;
use App\Traits\Models\Schedule\HasScheduleable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

/**
 * @property int                                           $id
 * @property \Illuminate\Support\Carbon|null               $start
 * @property \Illuminate\Support\Carbon|null               $end
 * @property \App\Models\Schedule\States\PlayoutState|null $status
 * @property bool                                          $is_active
 * @property \Illuminate\Support\Carbon|null               $created_at
 * @property \Illuminate\Support\Carbon|null               $updated_at
 * @property \Illuminate\Support\Carbon|null               $deleted_at
 * @method \Illuminate\Database\Eloquent\Builder whereHasSchedule(int|array $id)
 * @method \Illuminate\Database\Eloquent\Builder whereHasAnyChannel()
 * @method \Illuminate\Database\Eloquent\Builder whereHasChannel(int|Channel $channel)
 * @method \Illuminate\Database\Eloquent\Builder whereHasAncestralSchedule(int|array $schedule, bool $or=false)
 * @method \Illuminate\Database\Eloquent\Builder orWhereHasAncestralSchedule(int|array $schedule)
 * @method \Illuminate\Database\Eloquent\Builder whereHasPlayingChannel(int|Channel $channel=null)
 * @method \Illuminate\Database\Eloquent\Builder ongoing(string|DateTimeInterface $datetime = null, int $limit = self::DEFAULT_LIMIT)
 * @method \Illuminate\Database\Eloquent\Builder upcoming(string|DateTimeInterface $datetime = null, int $limit = self::DEFAULT_LIMIT)
 * @method \Illuminate\Database\Eloquent\Builder ongoingOrUpcoming(string|DateTimeInterface $datetime = null, int $limit = self::DEFAULT_LIMIT)
 *
 * @deprecated
 */
class ScheduleOccurrence extends Model
{
    use HasFactory, HasStates, HasPlayoutState;

    protected $fillable = [
        'start',
        'end',
        'status',
        'is_active',
    ];

    protected $casts = [
        'start'      => 'datetime',
        'end'        => 'datetime',
        'status'     => PlayoutState::class,
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @var int - the max number of occurrences to generate at one time */
    public const DEFAULT_LIMIT = 50;

    public function rules()
    {
        return $this->hasManyThrough(ScheduleRule::class, Schedule::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Has one Scheduleable entity through Schedule (polymorphic)
     */
    public function getScheduleable()
    {
        return $this->schedule->scheduleable;
    }

    /**
     * Has one Channel through polymorphic Scheduleable entity
     */
    public function channel()
    {
        return $this->scheduleable()->channel();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsActive(Builder $query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope for querying occurrences that have a specific schedule
     *
     * @param Builder        $query
     * @param array|int|null $schedule_id
     *
     * @return Builder
     */
    public function scopeWhereHasSchedule(Builder $query, array|int $schedule_id)
    {
        $schedule_ids = is_array($schedule_id) ? $schedule_id : [$schedule_id];
        return $query->whereHas('schedule', function (Builder $query) use ($schedule_ids) {
            $query->whereIn('id', $schedule_ids);
        });
    }

    /**
     * Scope for querying occurrences whose schedule's scheduleable's parent has a specific schedule
     * Ex) Deleting schedule rule of a layer/playlist should also delete
     *     occurrences tied to its child page and grandchild pages
     *
     * @param Builder   $query
     * @param array|int $schedule_id
     * @param bool      $or
     *
     * @return Builder
     */
    public function scopeWhereHasAncestralSchedule(Builder $query, array|int $schedule_id, bool $or=false)
    {
        $schedule_ids = is_array($schedule_id) ? $schedule_id : [$schedule_id];
        $operation = $or ? 'orWhereHas' : 'whereHas';
        return $query->$operation('schedule', function (Builder $query) use ($schedule_ids) {
            $query->whereHas('parentable', function($query) use ($schedule_ids) {
                $query->whereHas('schedule', function($query) use ($schedule_ids) {
                    $query->whereIn('id', $schedule_ids);
                });
            });
        });
    }

    /**
     * Alias for scopeWhereHasAncestralSchedule() using `orWhereHas`
     *
     * @param Builder        $query
     * @param array|int|null $schedule_id
     *
     * @return Builder
     */
    public function scopeOrWhereHasAncestralSchedule(Builder $query, array|int $schedule_id)
    {
        return $query->whereHasAncestralSchedule($schedule_id, true);
    }

    /**
     * Scope for querying occurrences whose schedule's scheduleable (Page) has a channel assigned
     *
     * @param Builder        $query
     *
     * @return Builder
     */
    public function scopeWhereHasAnyChannel(Builder $query)
    {
        return $query->whereHas('schedule', function($query){
            /** @see HasScheduleable::scopeWhereHasAnyChannel() */
            $query->whereHasAnyChannel();
        });
    }

    /**
     * Scope for querying occurrences whose schedule's scheduleable (Page) has a specific channel assigned
     *
     * @param Builder        $query
     * @param array|int|null $schedule_id
     *
     * @return Builder
     */
    public function scopeWhereHasChannel(Builder $query, Channel|int $channel)
    {
        $channel_id = is_int($channel) ? $channel : $channel->id;
        return $query->whereHas('schedule', function (Builder $query) use ($channel_id) {
            /** @see HasScheduleable::scopeWhereHasChannel() */
            $query->whereHasChannel($channel_id);
        });
    }

    /**
     * Scope for querying occurrences whose schedule's scheduleable (Page) has any channel, or a specific Channel,
     * that is currently playing
     *
     * @param Builder          $query
     * @param Channel|int|null $channel
     *
     * @return Builder
     */
    public function scopeWhereHasPlayingChannel(Builder $query, Channel|int $channel=null)
    {
        return $query->whereHas('schedule', function($query) use ($channel) {
            /** @see HasScheduleable::scopeWhereHasPlayingChannel() */
            $query->whereHasPlayingChannel($channel);
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpcoming(
        Builder $query,
        string|DateTimeInterface $datetime = null,
        int $limit = self::DEFAULT_LIMIT
    ) {
        $datetime ??= now();
        return $query->whereStatus(Next::class)
                     ->orWhere('start', '>=', $datetime)
                     ->orderBy('start', 'ASC')
                     ->limit($limit)
            ;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOngoing(
        Builder $query,
        string|DateTimeInterface $datetime = null,
        int $limit = self::DEFAULT_LIMIT
    ) {
        $datetime ??= now();
        return $query->whereStatus(Playing::class)
                     ->orWhere('start', '<', $datetime)
                     ->orderBy('start', 'ASC')
                     ->limit($limit)
            ;
    }

    public function scopeOngoingOrUpcoming(
        Builder $query,
        string|DateTimeInterface $datetime = null,
        int $limit = self::DEFAULT_LIMIT
    ) {
        $datetime ??= now();
        return $query->where(function($query) use ($datetime) {
            $query->whereStatus(Playing::class)
                  ->orWhere('start', '<', $datetime);
        })->orWhere(function($query) use ($datetime) {
            $query->whereStatus(Next::class)
                  ->orWhere('start', '>=', $datetime);
        })->orderBy('start', 'ASC')
          ->limit($limit)
        ;
    }
}
