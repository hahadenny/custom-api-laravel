<?php

namespace App\Models;

use App\Contracts\Models\GroupInterface;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\User> $users
 * @property-read \App\Models\UserGroup|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UserGroup> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UserGroup> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UserGroup> $descendants
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ChannelPermissionable> $channelPermissionables
 */
class UserGroup extends Model implements GroupInterface
{
    use BelongsToCompany, HasFactory, NodeTrait, SoftDeletes, SetsRelationAlias, SetsRelationAlias;

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['users' => 'items'];

    protected $fillable = [
        'name',
        'parent_id',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function items(): HasMany
    {
        return $this->users();
    }

    public function channelPermissionables(): MorphMany
    {
        return $this->morphMany(ChannelPermissionable::class, 'targetable');
    }
}
