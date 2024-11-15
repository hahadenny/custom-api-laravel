<?php

namespace App\Models;

use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\TreeSortable;
use App\Models\Schedule\ScheduleListing;
use App\Services\PlaylistService;
use App\Traits\Models\ListingPivotTrait;
use App\Traits\Models\TreeSortableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property int $id
 * @property int $project_id
 * @property string $projectable_type
 * @property int $projectable_id
 * @property int|null $group_id
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Project $project
 * @property-read \App\Models\Playlist|\App\Models\PlaylistGroup $projectable
 * @property-read \App\Models\PlaylistGroup|null $group
 */
class ProjectListing extends Model implements ListingPivotInterface, TreeSortable
{
    use HasFactory, ListingPivotTrait, SoftDeletes, TreeSortableTrait;

    protected $fillable = [
        'project_id',
        'projectable_type',
        'projectable_id',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function projectable()
    {
        return $this->morphTo();
    }

    public function group()
    {
        return $this->belongsTo(PlaylistGroup::class);
    }

    // inverse of
    // ScheduleListing::listing()
    public function scheduleListingPivots()
    {
        return $this->morphMany(ScheduleListing::class, 'listing');
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where([
            'project_id' => $this->project_id,
        ]);
    }

    public function associateList($model): void
    {
        $this->project()->associate($model);
    }

    public function associateListable($model): void
    {
        $this->projectable()->associate($model);
    }

    public function isBelongedToList(Model|int $model): bool
    {
        return $this->project_id === ($model instanceof Model ? $model->id : $model);
    }

    public function buildTreeToUpdateSort(): SupportCollection
    {
        return (new PlaylistService())->buildTreeToUpdateSort($this->project);
    }
}
