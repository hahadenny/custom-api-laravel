<?php

namespace App\Models;

use App\Contracts\Models\ScheduleableInterface;
use App\Contracts\Models\ScheduleableParentInterface;
use App\Models\Schedule\ScheduleSet;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingParentTrait;
use App\Traits\Models\Schedule\IsScheduleable;
use App\Traits\Models\SortableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Layer in the Scheduler
 *
 * NOTE: Not related to Channel; name is from the original req in which they were related.
 *       The scheduleable_type for morph relations in the tables makes it a bit too
 *       much of a pain to change
 *
 * @property int                             $id
 * @property string                          $name
 * @property string|null                     $description
 * @property int                             $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User           $createdBy
 */
class ChannelLayer extends Model implements ScheduleableInterface, ScheduleableParentInterface
{
    use HasFactory, SoftDeletes, SortableTrait, ListingParentTrait, IsScheduleable, BelongsToCompany;

    protected $fillable = [
        'schedule_set_id',
        'name',
        'description',
        'sort_order',
    ];


    protected $appends = [
        // 'sort_order',
    ];

    // formerly channel()
    public function scheduleSet()
    {
        return $this->belongsTo(ScheduleSet::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Take the specified grouping context into consideration when sorting
     *
     * @see https://github.com/spatie/eloquent-sortable#grouping
     * @see \App\Traits\Models\SortableTrait
     */
    public function buildSortQuery()
    {
        return static::query()->where('channel_id', $this->channel_id);
    }
}
