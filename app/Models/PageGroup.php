<?php

namespace App\Models;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ScheduleableInterface;
use App\Contracts\Models\ScheduleableParentInterface;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingGroupTrait;
use App\Traits\Models\ListingSortableTrait;
use App\Traits\Models\Schedule\IsScheduleable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @property int $id
 * @property int $company_id
 * @property int $playlist_id
 * @property string $name
 * @property string|null $color
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Playlist $playlist
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page> $listingPages
 * @property-read \App\Models\PageGroup|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PageGroup> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PageGroup> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PageGroup> $descendants
 * @property-read \App\Models\ChangeLog|null $changeLog
 */
class PageGroup extends Model implements GroupInterface, ScheduleableInterface, ScheduleableParentInterface
{
    use BelongsToCompany, HasFactory, ListingGroupTrait, ListingSortableTrait, NodeTrait, SoftDeletes, IsScheduleable;

    protected $fillable = [
        'name',
        'color',
        'parent_id',
    ];

    protected $hidden = [
        'deleted_at',
        'pivot',
        'parentListingPivot',
        'listingPages',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'sort_order',
    ];

    public function playlist()
    {
        return $this->belongsTo(Playlist::class);
    }

    public function items(): MorphToMany
    {
        return $this->listingPages();
    }

    public function listingPages()
    {
        return $this->listingItems(Page::class, 'playlistable', 'playlist_listings')
            ->withPivot(['playlist_id']);
    }

    public function changeLog()
    {
        return $this->morphOne(ChangeLog::class, 'changeable');
    }

    public function parentListingPivot(): MorphOne
    {
        return $this->morphOne(PlaylistListing::class, 'playlistable');
    }
}
