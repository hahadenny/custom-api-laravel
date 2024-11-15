<?php

namespace App\Models;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ScheduleableInterface;
use App\Contracts\Models\ScheduleableParentInterface;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingGroupTrait;
use App\Traits\Models\ListingSortableTrait;
use App\Traits\Models\Schedule\IsScheduleable;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property string $name
 * @property int $created_by
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Project $project
 * @property-read \App\Models\User $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Playlist> $playlists
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Playlist> $listingPlaylists
 * @property-read \App\Models\PlaylistGroup|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PlaylistGroup> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PlaylistGroup> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PlaylistGroup> $descendants
 */
class PlaylistGroup extends Model implements GroupInterface, ScheduleableInterface, ScheduleableParentInterface
{
    use BelongsToCompany, HasFactory, ListingGroupTrait, ListingSortableTrait, NodeTrait, SoftDeletes, SetsRelationAlias, IsScheduleable;

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['playlists' => 'items', 'items' => 'playlists'];

    protected $fillable = [
        'name',
        'parent_id',
    ];

    protected $hidden = [
        'deleted_at',
        'pivot',
        'parentListingPivot',
        'listingPlaylists',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'sort_order',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    public function items(): HasMany
    {
        return $this->playlists();
    }

    public function listingPlaylists()
    {
        return $this->listingItems(Playlist::class, 'projectable', 'project_listings')
            ->withPivot(['project_id']);
    }

    public function parentListingPivot(): MorphOne
    {
        return $this->morphOne(ProjectListing::class, 'projectable');
    }
}
