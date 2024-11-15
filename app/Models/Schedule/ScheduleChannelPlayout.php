<?php

namespace App\Models\Schedule;

use App\Models\Channel;
use App\Models\Schedule\States\PlayoutState;
use App\Models\User;
use App\Traits\Models\Schedule\HasPlayoutState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

/**
 * Essentially an entry in the queue for pages pending immediate-future
 * playout for a channel.
 *
 * Saving this info to the DB is more reliable than a redis cache and
 * more scalable than a file cache.
 *
 * @property int                             $id
 * @property int                             $playout_channel_id
 * @property int                             $schedule_set_id
 * @property int                             $schedule_listing_id
 * @property int|null                        $next_id
 * @property \Illuminate\Support\Carbon|null $start
 * @property \Illuminate\Support\Carbon|null $end
 * @property int|null                        $elapsed
 * @property int|null                        $remaining
 * @property int|null                        $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Channel                    $playoutChannel
 * @property-read Channel                    $scheduleSet
 * @property-read ScheduleChannelPlayout     $next
 * @property-read ScheduleChannelPlayout     $previous
 * @property-read ScheduleListing            $listing
 * @property-read User|null                  $createdBy
 *
 * @method \Illuminate\Database\Eloquent\Builder bySetStatus(int|ScheduleSet $set, string|PlayoutState $status)
 * @method \Illuminate\Database\Eloquent\Builder forLoopOfSet(int|ScheduleSet $set, string|PlayoutState $status)
 * @method \Illuminate\Database\Eloquent\Builder whereAnySetHasSameStatus(string|PlayoutState $status)
 */
class ScheduleChannelPlayout extends Model
{
    use HasStates, HasPlayoutState;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'created_by',
    ];

    protected $casts = [
        'start'  => 'datetime',
        'end'    => 'datetime',
        'status' => PlayoutState::class,
    ];

    protected $with = [
        'listing',
        'next',
    ];

    public function playoutChannel()
    {
        return $this->belongsTo(Channel::class);
    }

    // formerly listingChannel()
    public function scheduleSet()
    {
        return $this->belongsTo(ScheduleSet::class);
    }

    public function next()
    {
        return $this->belongsTo(ScheduleChannelPlayout::class);
    }

    // inverse of ChannelPlayout::next()
    public function previous()
    {
        return $this->hasMany(ScheduleChannelPlayout::class, 'next_id');
    }

    public function listing()
    {
        return $this->belongsTo(ScheduleListing::class, 'schedule_listing_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeWhereSetStatus(Builder $query, int|ScheduleSet $set, string|PlayoutState $status)
    {
        $set_id = $set instanceof ScheduleSet ? $set->id : $set;
        return $query->whereStatus($status)
                     ->where('schedule_set_id', $set_id);
    }

    public function scopeForLoopOfSet(Builder $query, int|ScheduleSet $set, string|PlayoutState $status)
    {
        return $query->with([
            'listing.schedule.rules',
            'listing.scheduleable',
            'next.listing.schedule.rules',
            'next.listing.scheduleable',
        ])->whereSetStatus($set, $status)
          ->whereNotNull('next_id');
    }

    public function scopeWhereAnySetHasSameStatus(Builder $query, string|PlayoutState $status)
    {
        return $query->whereStatus($status)
                     ->whereHas('scheduleSet', function($query) use ($status) {
                         $query->whereStatus($status);
                     });
    }
}
