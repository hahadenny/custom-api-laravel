<?php

namespace App\Models;

use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ScheduleableInterface;
use App\Contracts\Models\ScheduleableParentInterface;
use App\Enums\PlaylistType;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingChildTrait;
use App\Traits\Models\ListingParentTrait;
use App\Traits\Models\ListingSortableTrait;
use App\Traits\Models\Schedule\IsScheduleable;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                                                                        $id
 * @property int                                                                        $company_id
 * @property int                                                                        $project_id
 * @property int|null                                                                   $playlist_group_id
 * @property string                                                                     $name
 * @property string                                                                     $type
 * @property bool                                                                       $is_active
 * @property int                                                                        $created_by
 * @property \Illuminate\Support\Carbon|null                                            $created_at
 * @property \Illuminate\Support\Carbon|null                                            $updated_at
 * @property \Illuminate\Support\Carbon|null                                            $deleted_at
 * @property-read \App\Models\Company                                                   $company
 * @property-read \App\Models\Project                                                   $project
 * @property-read \App\Models\PlaylistGroup|null                                        $group
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page>            $pages
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page>            $pagesWithPivotTrashed
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PageGroup>       $pageGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PlaylistListing> $listingPivots
 * @property-read \App\Models\User                                                      $createdBy
 */
class Playlist extends Model implements ItemInterface, ScheduleableInterface, ScheduleableParentInterface
{
    use BelongsToCompany, HasFactory, ListingSortableTrait, SoftDeletes, ListingParentTrait, ListingChildTrait, SetsRelationAlias, IsScheduleable;

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['playlistGroup' => 'group'];


    protected $fillable = [
        'playlist_group_id',
        'name',
        'type',
        'is_active',
    ];

    protected $hidden = [
        'deleted_at',
        'pivot',
        'parentListingPivot',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'sort_order',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $playlist) {
            if (is_null($playlist->type)) {
                $playlist->type = PlaylistType::Manual->value;
            }
        });

        static::deleting(function (self $playlist) {
            // soft delete imitation of ON DELETE CASCADE
            foreach ($playlist->pages()->get() as $page) {
                /** @var \App\Models\Page $page */
                $page->delete();
            }

            foreach ($playlist->pageGroups()->get() as $pageGroup) {
                /** @var \App\Models\PageGroup $pageGroup */
                $pageGroup->delete();
            }
        });
    }

    public function getGroupId() : ?int
    {
        return $this->playlist_group_id;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function playlistGroup()
    {
        return $this->belongsTo(PlaylistGroup::class);
    }

    public function group() : BelongsTo
    {
        return $this->playlistGroup();
    }

    public function pages()
    {
        return $this->listingChildren(
            Page::class,
            'playlistable',
            'playlist_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function pagesWithPivotTrashed()
    {
        return $this->listingChildrenWithPivotTrashed(
            Page::class,
            'playlistable',
            'playlist_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function pageGroups()
    {
        return $this->hasMany(PageGroup::class);
    }

    public function listingPageGroups()
    {
        return $this->listingChildren(
            PageGroup::class,
            'playlistable',
            'playlist_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function listingPivots()
    {
        return $this->hasMany(PlaylistListing::class);
    }

    public function createdBy() : BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function parentListingPivot() : MorphOne
    {
        return $this->morphOne(ProjectListing::class, 'projectable');
    }
}
