<?php

namespace App\Models;

use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\TreeSortable;
use App\Services\ChannelService;
use App\Traits\Models\ListingPivotTrait;
use App\Traits\Models\TreeSortableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property int $id
 * @property int $company_id
 * @property string $companyable_type
 * @property int $companyable_id
 * @property int|null $group_id
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Company $parent
 * @property-read \App\Models\Channel|\App\Models\ChannelGroup $companyable
 * @property-read \App\Models\ChannelGroup|null $group
 */
class CompanyChannelListing extends Model implements ListingPivotInterface, TreeSortable
{
    use HasFactory, ListingPivotTrait, SoftDeletes, TreeSortableTrait;

    protected $fillable = [
        'company_id',
        'companyable_type',
        'companyable_id',
    ];
    protected $hidden = [
        'company_id',
        'created_by',
        'created_at',
        'updated_at'
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function parent() : BelongsTo
    {
        return $this->channel();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function companyable()
    {
        return $this->morphTo();
    }

    public function group()
    {
        return $this->belongsTo(ChannelGroup::class);
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where([
            'company_id' => $this->company_id,
        ]);
    }

    public function associateList($model): void
    {
        $this->company()->associate($model);
    }

    public function associateListable($model): void
    {
        $this->companyable()->associate($model);
    }

    public function isBelongedToList(Model|int $model): bool
    {
        return $this->company_id === ($model instanceof Model ? $model->id : $model);
    }

    public function buildTreeToUpdateSort(): SupportCollection
    {
        return (new ChannelService())->buildTreeToUpdateSort($this->company);
    }
}
