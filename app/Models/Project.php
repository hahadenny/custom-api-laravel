<?php

namespace App\Models;

use App\Models\Schedule\ScheduleSet;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingParentTrait;
use App\Traits\Models\SortableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $workspace_id
 * @property string $name
 * @property int $sort_order
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $createdBy
 * @property-read \App\Models\Workspace|null $activeWorkspace The last workspace saved for this project.
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Workspace> $workspaces
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UserProject> $userProjects
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Playlist> $playlists
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PlaylistGroup> $playlistGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Playlist> $listingPlaylists
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PlaylistGroup> $listingPlaylistGroups
 */
class Project extends Model implements Sortable
{
    use BelongsToCompany, HasFactory, SoftDeletes, SortableTrait, ListingParentTrait;

    protected $fillable = [
        'name',
        'workspace_id',
    ];

    protected ?Workspace $workspace = null;

    protected ?UserProject $userProject = null;

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (self $project) {
            foreach ($project->playlists()->get() as $playlist) {
                /** @var \App\Models\Playlist $playlist */
                $playlist->delete();
            }

            foreach ($project->playlistGroups()->get() as $playlistGroup) {
                /** @var \App\Models\PlaylistGroup $playlistGroup */
                $playlistGroup->delete();
            }

            foreach ($project->userProjects()->get() as $userProject) {
                /** @var \App\Models\UserProject $userProject */
                $userProject->delete();
            }
        });
    }

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): static
    {
        $this->workspace = $workspace;
        return $this;
    }

    public function getUserProject(): ?UserProject
    {
        return $this->userProject;
    }

    public function setUserProject(?UserProject $userProject): static
    {
        $this->userProject = $userProject;
        return $this;
    }

    public function setUserProjectFromMany(Collection $userProjects, User $user): static
    {
        $this->userProject = $userProjects
            ->where('user_id', $user->id)
            ->where('project_id', $this->id)
            ->first();

        return $this;
    }

    public function activeWorkspace()
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class);
    }

    public function userProjects()
    {
        return $this->hasMany(UserProject::class);
    }

    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    public function playlistGroups()
    {
        return $this->hasMany(PlaylistGroup::class);
    }

    public function listingPlaylists()
    {
        return $this->listingChildren(
            Playlist::class,
            'projectable',
            'project_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function listingPlaylistGroups()
    {
        return $this->listingChildren(
            PlaylistGroup::class,
            'projectable',
            'project_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function scheduleSets()
    {
        return $this->hasMany(ScheduleSet::class);
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where([
            'company_id' => $this->company_id,
        ]);
    }
}
