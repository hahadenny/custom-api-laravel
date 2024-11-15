<?php

namespace App\Traits\Models\Schedule;

use App\Models\Schedule\States\Failed;
use App\Models\Schedule\States\Finished;
use App\Models\Schedule\States\Idle;
use App\Models\Schedule\States\Next;
use App\Models\Schedule\States\Paused;
use App\Models\Schedule\States\Playing;
use App\Models\Schedule\States\Scheduled;
use App\Models\Schedule\States\Skipped;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model has schedule related status states
 *
 * @method \Illuminate\Database\Eloquent\Builder whereState(string $column, $states)
 * @method \Illuminate\Database\Eloquent\Builder orWhereState(string $column, $states)
 * @method \Illuminate\Database\Eloquent\Builder whereStatus(string|array $states, string $column='status')
 * @method \Illuminate\Database\Eloquent\Builder wherePlaying()
 * @method \Illuminate\Database\Eloquent\Builder wherePaused()
 * @method \Illuminate\Database\Eloquent\Builder wherePlayingNext()
 * @method \Illuminate\Database\Eloquent\Builder wherePlayoutNext()
 * @method \Illuminate\Database\Eloquent\Builder whereNext()
 * @method \Illuminate\Database\Eloquent\Builder wherePlayoutFinished()
 * @method \Illuminate\Database\Eloquent\Builder whereFinished()
 * @method \Illuminate\Database\Eloquent\Builder whereIdle()
 * @method \Illuminate\Database\Eloquent\Builder whereScheduledForPlayout()
 * @method \Illuminate\Database\Eloquent\Builder whereScheduled()
 * @method \Illuminate\Database\Eloquent\Builder whereSkippedPlayout()
 * @method \Illuminate\Database\Eloquent\Builder whereSkipped()
 * @method \Illuminate\Database\Eloquent\Builder wherePlayoutFailed()
 * @method \Illuminate\Database\Eloquent\Builder whereFailed()
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 * @mixin \Spatie\ModelStates\HasStates
 */
trait HasPlayoutState
{
    /**********************************************
     * Checks
     **********************************************/

    public function hasStatus(string $statusClass) : bool
    {
        return $this->status->getValue() === $statusClass;
    }

    public function isIdle() : bool
    {
        return $this->status->getValue() === Idle::$name;
    }

    public function isPlaying() : bool
    {
        return $this->status->getValue() === Playing::$name;
    }

    public function isNext() : bool
    {
        return $this->status->getValue() === Next::$name;
    }

    public function isPaused() : bool
    {
        return $this->status->getValue() === Paused::$name;
    }

    public function isFinished() : bool
    {
        return $this->status->getValue() === Finished::$name;
    }

    public function isScheduled() : bool
    {
        return $this->status->getValue() === Scheduled::$name;
    }

    public function wasSkipped() : bool
    {
        return $this->status->getValue() === Skipped::$name;
    }

    public function playoutFailed() : bool
    {
        return $this->status->getValue() === Failed::$name;
    }

    /**********************************************
     * Scopes
     **********************************************/

    /**
     * @todo look into why not working
     * Shortcut to spatie-model-states scope
     *
     * @return Builder
     */
    public function scopeWhereStatus(Builder $query, string|array $states, string $column='status')
    {
        return static::whereState($column, $states);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePlaying(Builder $query)
    {
        return $query->whereStatus(Playing::class);
    }

    public function scopeWherePaused(Builder $query)
    {
        return $query->whereStatus(Paused::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePlayoutNext(Builder $query)
    {
        return $query->whereStatus(Next::class);
    }

    public function scopeWherePlayingNext(Builder $query)
    {
        return $query->wherePlayoutNext();
    }

    public function scopeWhereNext(Builder $query)
    {
        return $query->wherePlayoutNext();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePlayoutFinished(Builder $query)
    {
        return $query->whereStatus(Finished::class);
    }

    public function scopeWhereFinished(Builder $query)
    {
        return $query->wherePlayoutFinished();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereIdle(Builder $query)
    {
        return $query->whereStatus(Idle::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereScheduledForPlayout(Builder $query)
    {
        return $query->whereStatus(Scheduled::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereSkippedPlayout(Builder $query)
    {
        return $query->whereStatus(Skipped::class);
    }

    public function scopeWhereSkipped(Builder $query)
    {
        return $query->whereSkippedPlayout();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePlayoutFailed(Builder $query)
    {
        return $query->whereStatus(Failed::class);
    }

    public function scopeWhereFailed(Builder $query)
    {
        return $query->wherePlayoutFailed();
    }
}
