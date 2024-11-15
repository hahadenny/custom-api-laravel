<?php

namespace App\Traits\Models\Schedule;

use App\Models\Channel;
use App\Models\Page;
use App\Models\Playlist;
use App\Models\Schedule\States\Playing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Models that have a morphTo relation with a model that implements \App\Contracts\Models\ScheduleableInterface
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 * @property int                                              $scheduleable_id
 * @property string                                           $scheduleable_type
 * @property-read \App\Contracts\Models\ScheduleableInterface $scheduleable
 *
 * @method \Illuminate\Database\Eloquent\Builder whereHasScheduleables(int|array $id = null, string|array $scheduleableClasses = '*')
 * @method \Illuminate\Database\Eloquent\Builder whereScheduleables(int|array $id, string|array $scheduleableClasses = '*')
 * @method \Illuminate\Database\Eloquent\Builder whereHasPlaylists(int|array $id = null)
 * @method \Illuminate\Database\Eloquent\Builder whereHasPages(int|array $id = null)
 * @method \Illuminate\Database\Eloquent\Builder wherePlaylists(int|array $id)
 * @method \Illuminate\Database\Eloquent\Builder wherePages(int|array $id)
 * @method \Illuminate\Database\Eloquent\Builder whereHasAnyChannel()
 * @method \Illuminate\Database\Eloquent\Builder whereHasChannel(int|Channel $channel)
 * @method \Illuminate\Database\Eloquent\Builder whereHasPlayingChannel(int|Channel $channel=null)
 */
trait HasScheduleable
{
    public function scheduleable() : MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Find models that belong any Scheduleable entity
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array                          $scheduleableClasses - string or array of FQCN that
     *                                                                     implement ScheduleableInterface
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereHasScheduleables(
        Builder      $query,
        string|array $scheduleableClasses = '*',
    ) {
        return $query->whereHasMorph(
            'scheduleable',
            $scheduleableClasses,
        );
    }

    /**
     * Find models belonging to a specific Scheduleable entity or entities
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|array                             $id                  - playlist id or array of ids
     * @param string|array                          $scheduleableClasses - string or array of FQCN that
     *                                                                     implement ScheduleableInterface
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereScheduleables(
        Builder      $query,
        int|array    $id,
        string|array $scheduleableClasses = '*',
    ) {
        return $query->whereHasMorph(
            'scheduleable',
            $scheduleableClasses,
            function (Builder $query) use ($id) {
                if (is_array($id)) {
                    return $query->whereIn('id', $id);
                }
                return $query->where('id', $id);
            }
        );
    }

    /**
     * Find models that belong to any Playlists or to a specific Playlist
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array|int|null                        $id - playlist id or array of ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereHasPlaylists(Builder $query, array|int $id = null)
    {
        if (isset($id)) {
            return $query->whereScheduleables($id, Playlist::class);
        }
        return $query->whereHasScheduleables(Playlist::class);
    }

    /**
     * Find models that belong to any Pages or to a specific Page
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array|int|null                        $id - page id or array of ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereHasPages(Builder $query, array|int $id = null)
    {
        if (isset($id)) {
            return $query->whereScheduleables($id, Page::class);
        }
        return $query->whereHasScheduleables(Page::class);
    }

    /**
     * Find models belonging to a Playlist or set of Playlists
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|array                             $id - playlist id or array of ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePlaylists($query, int|array $id)
    {
        return $this->whereHasPlaylists($id);
    }

    /**
     * Find models belonging to a Page or set of Pages
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|array                             $id - page id or array of ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePages($query, int|array $id)
    {
        return $this->whereHasPages($id);
    }

    /**
     * Scope for querying schedules whose scheduleable (Page) has a channel
     */
    public function scopeWhereHasAnyChannel(Builder $query) : Builder
    {
        return $query->whereHasMorph('scheduleable', Page::class, function($query){
            $query->has('channel');
        });
    }

    /**
     * Scope for querying schedules whose scheduleable (Page) has any channel, or a specific Channel,
     * that is currently playing
     */
    public function scopeWhereHasPlayingChannel(Builder $query, Channel|int $channel=null) : Builder
    {
        $channel_id = is_int($channel) ? $channel : $channel?->id;
        return $query->whereHasMorph('scheduleable', Page::class, function($query) use ($channel_id) {
            $query->whereHas('channel', function($query) use ($channel_id) {
                if($channel_id !== null){
                    $query->where('id', '=', $channel_id)->where('status', '=', Playing::$name);
                } else {
                    $query->where('status', '=', Playing::$name);
                }
            });
        });
    }

    /**
     * Scope for querying schedules whose scheduleable (Page) has a specific channel assigned
     */
    public function scopeWhereHasChannel(Builder $query, Channel|int $channel) : Builder
    {
        $channel_id = is_int($channel) ? $channel : $channel->id;
        return $query->whereHasMorph('scheduleable', Page::class, function($query) use ($channel_id) {
            $query->whereRelation('channel', 'id', '=', $channel_id);
        });
    }

    /**
     * Find and return models belonging to a Page or set of Pages
     *
     * @param array|int|null $id
     *
     * @return \Illuminate\Database\Eloquent\Collection<Page>
     */
    public function getPages(array|int $id = null) : Collection
    {
        return $this->whereHasPages($id)->get();
    }

    /**
     * Find and return models belonging to a Playlist or set of Playlists
     *
     * @param array|int|null $id
     *
     * @return \Illuminate\Database\Eloquent\Collection<Playlist>
     */
    public function getPlaylists(array|int $id = null) : Collection
    {
        return $this->whereHasPlaylists($id)->get();
    }
}
