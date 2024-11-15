<?php

namespace App\Models;

use App\Contracts\Models\ItemInterface;
use App\Enums\UserRole;
use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleChannelPlayout;
use App\Models\Schedule\ScheduleRule;
use App\Models\Schedule\ScheduleSet;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasPermissions;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $user_group_id
 * @property \App\Enums\UserRole $role
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\UserGroup|null $group
 * @property-read \App\Models\Profile|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Workspace> $workspaces
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UserProject> $userProjects
 * @property-read \App\Models\UserProject|null $userProject
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ChannelGroup> $channelGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\Spatie\Permission\Models\Permission> $permissions
 */
class User extends Authenticatable implements ItemInterface, JWTSubject
{
    use BelongsToCompany, HasApiTokens, HasFactory, Notifiable, SoftDeletes, SetsRelationAlias, HasPermissions;

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['userGroup' => 'group'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'user_group_id',
        'role',
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
        'email_verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'role' => UserRole::class,
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (self $user) {
            /** @var \App\Models\Profile $profile */
            $profile = $user->profile()->first();

            if (! is_null($profile)) {
                $profile->delete();
            }

            foreach ($user->userProjects()->get() as $userProject) {
                /** @var \App\Models\UserProject $userProject */
                $userProject->delete();
            }

            $workspaces = $user->workspaces()
                ->doesntHave('projects')
                ->doesntHave('activeInProjects')
                ->get();

            foreach ($workspaces as $workspace) {
                /** @var \App\Models\Workspace $workspace */
                $workspace->delete();
            }
        });
    }

    public function getGroupId(): ?int
    {
        return $this->user_group_id;
    }

    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class);
    }

    public function group(): BelongsTo
    {
        return $this->userGroup();
    }

    public function profile()
    {
        return $this->hasOne(Profile::class)->withDefault();
    }

    public function workspaces()
    {
        return $this->hasMany(Workspace::class);
    }

    public function userProjects()
    {
        return $this->hasMany(UserProject::class);
    }

    public function userProject()
    {
        return $this->hasOne(UserProject::class)->ofMany([], function ($query) {
            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query->where('is_active', true);
        });
    }

    public function channelGroups()
    {
        return $this->belongsToMany(ChannelGroup::class, 'channel_group_user');
    }

    public function scheduleSets() : BelongsToMany
    {
        return $this->belongsToMany(ScheduleSet::class, 'schedule_set_user')
                    ->withPivot('is_active')
                    ->latest('schedule_set_user.updated_at');
    }

    public function activeScheduleSets() : BelongsToMany
    {
        return $this->belongsToMany(ScheduleSet::class, 'schedule_set_user')
                    ->withPivot('is_active')
                    ->wherePivot('is_active', 1);
    }

    /**
     * Automatically creates hash for the user password.
     *
     * @param  string  $value
     * @return void
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function scopeIsSuperAdmin($query)
    {
        $this->scopeByRole($query, UserRole::SuperAdmin);
    }

    public function scopeIsAdmin($query)
    {
        $this->scopeByRole($query, UserRole::Admin);
    }

    public function scopeIsUser($query)
    {
        $this->scopeByRole($query, UserRole::User);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Enums\UserRole|\App\Enums\UserRole[]  $role
     * @return void
     */
    public function scopeByRole($query, UserRole|array $role)
    {
        if ($role instanceof UserRole) {
            $query->where('role', $role->value);
        } else {
            $query->whereIn('role', array_map(fn (UserRole $r): string => $r->value, $role));
        }
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isUser(): bool
    {
        return $this->role === UserRole::User;
    }

    public function owns(Model $model): bool
    {
        return $this->id === $model->user_id;
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function scheduleRules()
    {
        return $this->hasMany(ScheduleRule::class);
    }

    public function createdPlayouts()
    {
        return $this->hasMany(ScheduleChannelPlayout::class);
    }

    public function channelPermissionables(): MorphMany
    {
        return $this->morphMany(ChannelPermissionable::class, 'targetable');
    }

    /**
     * todo: move this
     *
     * @param User            $user
     * @param Channel         $channel
     *
     * @return bool
     */
    public static function checkChannelAccess(User $user, Channel $channel) : bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->channelGroups->count() === 0) {
            return true;
        }
        return $user->channelGroups->some(fn (ChannelGroup $group) => $group->channels->pluck('id')->contains($channel->id));
    }

    /**
     * todo: move this
     *
     * @param Channel $channel
     *
     * @return bool
     */
    public function hasChannelAccess(Channel $channel) : bool
    {
        return static::checkChannelAccess($this, $channel);
    }
}
