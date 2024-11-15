<?php

namespace App\Models;

use App\Contracts\Models\GroupInterface;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingGroupTrait;
use App\Traits\Models\ListingSortableTrait;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int $created_by
 * @property int|null $parent_id
 * @property boolean $is_preview
 * @property boolean $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Channel> $channels
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Channel> $listingChannels
 * @property-read \App\Models\ChannelGroup|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ChannelGroup> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ChannelGroup> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ChannelGroup> $descendants
 */
class ChannelGroup extends Model implements GroupInterface
{
    use BelongsToCompany, HasFactory, ListingGroupTrait, ListingSortableTrait, NodeTrait, SoftDeletes, SetsRelationAlias;

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['channels' => 'items'];

    protected $fillable = [
        'name',
        'parent_id',
        'is_preview',
        'is_default',
    ];

    protected $hidden = [
        'deleted_at',
        'pivot',
        'parentListingPivot',
        'listingChannels',
    ];

    protected $casts = [
        'is_preview' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'sort_order',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $group) {
            if ($group->is_default && $group->isDirty('is_default')) {

                $group->company->channels()
                    ->update(['is_default' => false]);

                $group->company->channelGroups()
                    ->whereKeyNot($group)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    public function items(): HasMany
    {
        return $this->channels();
    }

    public function listingChannels()
    {
        return $this->listingItems(Channel::class, 'companyable', 'company_channel_listings')
            ->withPivot(['company_id']);
    }

    public function parentListingPivot(): MorphOne
    {
        return $this->morphOne(CompanyChannelListing::class, 'companyable');
    }
}
