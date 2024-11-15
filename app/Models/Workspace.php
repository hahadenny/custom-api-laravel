<?php

namespace App\Models;

use App\Traits\Models\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int $user_id
 * @property string $name
 * @property bool $is_active
 * @property bool $auto_save
 * @property array $layout
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Project> $projects
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Project> $activeInProjects
 */
class Workspace extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'is_active',
        'auto_save',
        'layout',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'layout' => 'json',
        'is_active' => 'boolean',
        'auto_save' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $workspace) {
            if ($workspace->isDirty('is_active') && $workspace->is_active) {
                $workspace->user->workspaces()->whereKeyNot($workspace)->update(['is_active' => false]);
            }
        });

        static::deleting(function (self $workspace) {
            $workspace->projects()->detach();
            $workspace->activeInProjects()->update(['workspace_id' => null]);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }

    public function activeInProjects()
    {
        return $this->hasMany(Project::class);
    }
}
