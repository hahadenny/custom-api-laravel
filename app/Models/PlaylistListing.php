<?php

namespace App\Models;

use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\TreeSortable;
use App\Models\Schedule\ScheduleListing;
use App\Services\PageService;
use App\Traits\Models\ListingPivotTrait;
use App\Traits\Models\TreeSortableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property int                                         $id
 * @property int|null                                    $playlist_id
 * @property string                                      $playlistable_type
 * @property int                                         $playlistable_id
 * @property int                                         $company_id
 * @property int|null                                    $group_id
 * @property int                                         $sort_order
 * @property \Illuminate\Support\Carbon|null             $created_at
 * @property \Illuminate\Support\Carbon|null             $updated_at
 * @property \Illuminate\Support\Carbon|null             $deleted_at
 * @property-read \App\Models\Company                    $company
 * @property-read \App\Models\PageGroup|null             $group
 * @property-read \App\Models\Playlist|null              $playlist
 * @property-read \App\Models\Page|\App\Models\PageGroup $playlistable
 */
class PlaylistListing extends Model implements ListingPivotInterface, TreeSortable
{
    use HasFactory, ListingPivotTrait, SoftDeletes, TreeSortableTrait;

    protected $fillable = [
        'company_id',
        'playlist_id',
        'playlistable_type',
        'playlistable_id',
    ];

    public function playlist()
    {
        return $this->belongsTo(Playlist::class);
    }

    // inverse of Page::playlistListingPivots() (PlaylistListing)
    public function playlistable()
    {
        return $this->morphTo();
    }

    public function parent()
    {
        return $this->playlist();
    }

    public function company()
    {
        /** @var static|\Illuminate\Database\Eloquent\Model $this */
        return $this->belongsTo(Company::class);
    }

    public function group()
    {
        return $this->belongsTo(PageGroup::class);
    }

    // inverse of ScheduleListing::listing()
    public function scheduleListingPivots()
    {
        return $this->morphMany(ScheduleListing::class, 'listing');
    }

    // inverse of PlayoutHistory::listing()
    public function playoutHistory()
    {
        return $this->morphMany(PlayoutHistory::class, 'listing');
    }

    public function associateList($model) : void
    {
        $this->playlist()->associate($model);
    }

    public function associateListable($model) : void
    {
        $this->playlistable()->associate($model);
    }

    public function isBelongedToList(Model|int|null $model) : bool
    {
        return $this->playlist_id === ($model instanceof Model ? $model->id : $model);
    }

    public function buildSortQuery() : Builder
    {
        return static::query()->where([
            'company_id'  => $this->company_id,
            'playlist_id' => $this->playlist_id,
        ]);
    }

    public function buildTreeToUpdateSort() : SupportCollection
    {
        return (new PageService())->buildTreeToUpdateSort($this->playlist, $this->company);
    }
}
