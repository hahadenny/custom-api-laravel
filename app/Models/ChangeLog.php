<?php

namespace App\Models;

use App\Enums\ChangeLogAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This contains changes that can be restored.
 *
 * The parent_id contains an id of a ChangeLog record with changes
 * that caused changes in the current record.
 * For example, we have a group id = 10 that has an item id = 11.
 * The group is deleted, ChangeLog records will be:
 * [id = 1, changeable_type = group, changeable_id = 10, parent_id = null],
 * [id = 2, changeable_type = item, changeable_id = 11, parent_id = 1].
 * Thus, when we restore the group id = 10 we know that an item id = 11
 * should be restored with the group.
 * When those elements are deleted separately, for example, first we delete
 * the item and after that delete its group, then both ChangeLog records
 * will have parent_id = null. Thus, we know that when we restore the group,
 * its item should not be restored.
 * Only one level of nesting is allowed.
 *
 * @property int $id
 * @property string $action
 * @property string $changeable_type
 * @property int $changeable_id
 * @property int|null $user_id
 * @property array|null $data
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\SoftDeletes|null $changeable
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\ChangeLog|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ChangeLog> $children
 */
class ChangeLog extends Model
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'action' => ChangeLogAction::class,
        'data' => 'json',
    ];

    public function changeable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ChangeLog::class);
    }

    public function children()
    {
        return $this->hasMany(ChangeLog::class, 'parent_id');
    }
}
