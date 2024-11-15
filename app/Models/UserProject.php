<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property array|null $state
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Project $project
 */
class UserProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'state',
        'is_active',
    ];

    protected $hidden = [
        'created_at',
        'deleted_at',
        'user_id',
        'updated_at',
    ];
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'state' => 'json',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $userProject) {
            if ($userProject->isDirty('is_active') && $userProject->is_active) {
                $userProject->user->userProjects()->whereKeyNot($userProject)->update(['is_active' => false]);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
