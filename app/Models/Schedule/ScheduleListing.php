<?php

namespace App\Models\Schedule;

use App\Casts\ScheduleDurationCast;
use App\Contracts\Models\TreeSortable;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use App\Models\PlaylistListing;
use App\Models\PlayoutHistory;
use App\Models\ProjectListing;
use App\Models\Schedule\States\Finished;
use App\Models\Schedule\States\PlayoutState;
use App\Services\Schedule\ScheduleListingService;
use App\Traits\Models\ListingPivotTrait;
use App\Traits\Models\NodeTrait;
use App\Traits\Models\Schedule\BelongsToSchedule;
use App\Traits\Models\Schedule\HasPlayoutState;
use App\Traits\Models\Schedule\HasScheduleable;
use App\Traits\Models\TreeSortableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;

/**
 * A listing pivot between existing listing pivot entries (PlaylistListing/ProjectListing),
 * ScheduleableInterface models, their Schedules, and other scheduler-related data.
 *
 * NOTE: unofficially implements ListingPivotInterface (has no group() : BelongsTo)
 *
 * @property int                                                                              $id
 * @property int|null                                                                         $listing_id
 * @property string                                                                           $listing_type
 * @property int                                                                              $duration
 * @property \App\Models\Schedule\States\PlayoutState|string|null                             $status
 * @property int                                                                              $sort_order
 * @property \Illuminate\Support\Carbon|null                                                  $created_at
 * @property \Illuminate\Support\Carbon|null                                                  $updated_at
 * @property \Illuminate\Support\Carbon|null                                                  $deleted_at
 *
 * @property-read \Illuminate\Support\Collection<\App\Models\Schedule\ScheduleChannelPlayout> $playouts
 * @property-read \App\Models\PlaylistListing|\App\Models\ProjectListing                      $listing
 * @property-read \App\Models\Schedule\ScheduleSet                                            $scheduleSet
 *
 * @method Builder whereMatchesListing(ProjectListing|PlaylistListing|int $listing, string $listing_type=null)
 * @method Builder whereHasListingParent(ProjectListing|PlaylistListing|int $listing, string $listing_type=null)
 * @method Builder forCalendar(ScheduleSet $scheduleSet)
 */
class ScheduleListing extends Model implements TreeSortable
{
    use SoftDeletes, NodeTrait, ListingPivotTrait, BelongsToSchedule, HasScheduleable, TreeSortableTrait, HasPlayoutState;

    /** @const unsigned bigint (seconds) */
    public const DEFAULT_DURATION = 4;

    protected $guarded = [
        'id',
        '_lft',
        '_rgt',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'status' => PlayoutState::class,
        'duration' => ScheduleDurationCast::class,
    ];

    protected $with = [
        'scheduleable',
        'schedule',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $listing) {
            // Use duration if set, otherwise use the related scheduleable's duration
            if (is_null($listing->duration) || $listing->duration <= 0) {
                $listing->duration = $listing->scheduleable_type === Page::class
                    ? $listing?->scheduleable?->getDuration()
                    : 0;
            }
        });
    }

    // formerly channel()
    public function scheduleSet()
    {
        return $this->belongsTo(ScheduleSet::class);
    }

    public function playouts()
    {
        return $this->hasMany(ScheduleChannelPlayout::class, 'schedule_listing_id');
    }

    // inverse of
    // PlaylistListing::scheduleListingPivots()
    // ProjectListing::scheduleListingPivots()
    public function listing() : MorphTo
    {
        return $this->morphTo();
    }

    // inverse of PlayoutHistory::listing()
    public function playoutHistory() : MorphMany
    {
        return $this->morphMany(PlayoutHistory::class, 'listing');
    }

    public function finishedPlayoutHistory() : MorphMany
    {
        return $this->playoutHistory()->where('status', Finished::$name);
    }

    /**
     * Take the specified context into consideration when sorting
     *
     * @see https://github.com/spatie/eloquent-sortable#grouping
     * @see \App\Traits\Models\SortableTrait
     */
    public function buildSortQuery() : Builder
    {
        return static::query()->where([
            'parent_id' => $this->parent_id,
        ]);
    }

    /**
     * @throws \ErrorException
     */
    public function buildTreeToUpdateSort() : SupportCollection
    {
        return (new ScheduleListingService())->buildTreeToUpdateSort($this->scheduleSet, $this->parent);
    }

    /**
     * Override TreeSortable because we don't use pivot or GroupInterface
     *
     * @see \App\Traits\Models\TreeSortableTrait
     */
    protected static function updateSortOrderRecursive(\Illuminate\Support\Collection $tree, int $currentOrder): int
    {
        if ($tree->isEmpty()) {
            return $currentOrder;
        }

        foreach ($tree as $model) {
            /** @var ScheduleListing $model */
            $model->sort_order = $currentOrder;
            $model->update();
            $currentOrder++;

            if ($model->children()->count() > 0) {
                $currentOrder = static::updateSortOrderRecursive($model->children, $currentOrder);
            }
        }

        return $currentOrder;
    }

    /**
     * Get the total duration as sum of $this' descendants (if they exist)
     */
    public function getTotalDuration() : int
    {
        return $this->scheduleable_type === Page::class ? $this->duration
            : ($this->descendants?->sum(function ($descendant) {
                return $descendant->duration;
            }) ?? 0);
    }

    /**
     * Get the original listing pivot entry that this ScheduleListing is related to
     */
    public function getListing(int $listing_id, string $listing_type)
    {
        if($listing_type !== ProjectListing::class && $listing_type !== PlaylistListing::class){
            throw new \InvalidArgumentException("\$listing_type must be a valid ListingPivot related to ScheduleListing");
        }

        return $listing_type::findOrFail($listing_id);
    }

    // ##################################
    // # SCOPES
    //

    /**
     * Exact match
     */
    public function scopeWhereMatchesListing(Builder $query, ProjectListing|PlaylistListing|int $listing, string $listing_type = null) : Builder
    {
        $listing = is_int($listing) ? $this->getListing($listing, $listing_type) : $listing;

        if($listing::class === ProjectListing::class){
            $child_morph_id_col = 'projectable_id';
            $child_morph_type_col = 'projectable_type';
        } else {
            $child_morph_id_col = 'playlistable_id';
            $child_morph_type_col = 'playlistable_type';
        }

        return $query->where('listing_id', $listing->id)
                     ->where('listing_type', $listing::class)
                     ->where('scheduleable_id', $listing->$child_morph_id_col)
                     ->where('scheduleable_type', $listing->$child_morph_type_col)
            ;
    }

    /**
     * Query where the given listing pivot entry's "parent" (Playlist/PlaylistGroup/PageGroup) exists in the scheduler
     *
     * @param Builder                            $query
     * @param ProjectListing|PlaylistListing|int $listing
     * @param string|null                        $listing_type - FQCN of ProjectListing|PlaylistListing
     *
     * @return Builder
     */
    public function scopeWhereHasListingParent(Builder $query, ProjectListing|PlaylistListing|int $listing, string $listing_type = null) : Builder
    {
        $listing = is_int($listing) ? $this->getListing($listing, $listing_type) : $listing;

        if($listing::class === ProjectListing::class){
            // the only "parent" that matters in the project listing is a PlaylistGroup
            $parent_id_col = 'group_id';
            $parent_type = PlaylistGroup::class;
            return $query->where('scheduleable_id', $listing->$parent_id_col)
                  ->where('scheduleable_type', $parent_type);
        }

        return $query->where(function($query) use ($listing) {
            $query->where('scheduleable_id', $listing->playlist_id)
                  ->where('scheduleable_type', Playlist::class);
        })->orWhere(function($query) use ($listing) {
            $query->where('scheduleable_id', $listing->group_id)
                  ->where('scheduleable_type', PageGroup::class);
        });
    }

    public function scopeForCalendar(Builder $query, ScheduleSet $scheduleSet) : Builder
    {
        return $query
                    ->has('schedule.rules')
                    ->has('scheduleable')
                    ->where('schedule_set_id', $scheduleSet->id)
                    ->with(['children' => function(HasMany $query) {
                            $query->has('schedule.rules')->has('scheduleable');
                        },
                    ])
                    ->orderBy('sort_order');
    }
}
