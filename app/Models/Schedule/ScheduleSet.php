<?php

namespace App\Models\Schedule;

use App\Models\ChannelLayer;
use App\Models\Project;
use App\Models\Schedule\States\PlayoutState;
use App\Models\User;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingParentTrait;
use App\Traits\Models\Schedule\HasPlayoutState;
use App\Traits\Models\SortableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

/**
 * A group of ScheduleListings tied to a company project
 */
class ScheduleSet extends Model
{
    use BelongsToCompany, SoftDeletes, SortableTrait, ListingParentTrait, HasStates, HasPlayoutState;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'project_id',
    ];

    protected $hidden = [
        'deleted_at',
        'pivot',
        'parentListingPivot',
    ];

    protected $casts = [
        'status'     => PlayoutState::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $set) {
            // slug name used for redis/socket channel name
            if (empty($set->slug)) {
                $set->slug = Str::slug($set->name);
            }
            if (empty($set->sort_order)) {
                $set->sort_order = 1;
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function layers()
    {
        return $this->hasMany(ChannelLayer::class);
    }

    public function scheduleListings()
    {
        return $this->hasMany(ScheduleListing::class);
    }

    public function playoutListings()
    {
        return $this->hasMany(ScheduleChannelPlayout::class, 'schedule_set_id');
    }

    public function users() : BelongsToMany
    {
        return $this->belongsToMany(User::class, 'schedule_set_user')
                    ->withPivot('is_active')
                    ->latest('schedule_set_user.updated_at');
    }

    public function activeUsers() : BelongsToMany
    {
        return $this->belongsToMany(User::class, 'schedule_set_user')
                    ->withPivot('is_active')
                    ->wherePivot('is_active', 1)
                    ->latest('schedule_set_user.updated_at');
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where([
            'project_id' => $this->project_id,
        ]);
    }

    public function scopeWhereActive(Builder $query)
    {
        return $query->wherePivot('is_active', 1);
    }
}
