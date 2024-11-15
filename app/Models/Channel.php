<?php

namespace App\Models;

use App\Contracts\Models\ItemInterface;
use App\Models\Schedule\ScheduleChannelPlayout;
use App\Models\Schedule\States\PlayoutState;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingSortableTrait;
use App\Traits\Models\Schedule\HasPlayoutState;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $channel_group_id
 * @property string $name
 * @property array $addresses
 * @property string $stream_type
 * @property string $stream_url
 * @property boolean $is_default
 * @property boolean $is_preview
 * @property string $type
 * @property string|null $description
 * @property int|null $created_by
 * @property int|null $parent_id
 * @property \App\Models\Schedule\States\PlayoutState|string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\ChannelGroup|null $group
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Channel|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Channel> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page> $pages
 */
class Channel extends Model implements ItemInterface
{
    use BelongsToCompany, HasFactory, ListingSortableTrait, SoftDeletes, SetsRelationAlias, HasStates, HasPlayoutState;

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['channelGroup' => 'group'];

    public const TYPE_UNREAL = '/unreal';
    public const TYPE_DISGUISE = '/disguise';
    public const TYPE_AVALANCHE = 'avalanche';
    public const TYPE_DEFAULT = self::TYPE_UNREAL;

    public const STREAM_TYPE_VIDEO = 'video';
    public const STREAM_TYPE_WEBRTC = 'webrtc';
    public const STREAM_TYPE_DEFAULT = self::STREAM_TYPE_WEBRTC;

    protected $fillable = [
        'channel_group_id',
        'name',
        'description',
        'addresses',
        'is_default',
        'type',
        'stream_type',
        'stream_url',
        'parent_id',
        'status',
        'is_preview',
    ];

    protected $hidden = [
        'company_id',
        'deleted_at',
        'created_at',
        'created_by',
        'pivot',
        'parentListingPivot',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_preview' => 'boolean',
        'addresses' => 'array',
        'status'     => PlayoutState::class,
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

        static::saving(function (self $channel) {
            if (
                $channel->is_default
                && ($channel->isDirty('is_default') || $channel->isDirty('type'))
            ) {
                $trimmedType = ltrim($channel->type, '/');
                $channel->company->channelGroups()
                    ->update(['is_default' => false]);

                $channel->company->channels()
                    ->whereIn('type', [$trimmedType, '/'.$trimmedType])
                    ->whereKeyNot($channel)
                    ->update(['is_default' => false]);
            }

            if (empty($channel->stream_type)) {
                $channel->stream_type = self::STREAM_TYPE_DEFAULT;
            }

            if (empty($channel->type)) {
                $channel->type = self::TYPE_DEFAULT;
            }
        });
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
        parent::booted();

        // always constrain queries to render engine channels by default
        // (i.e., not Redis specific channels like Scheduler status)
        static::addGlobalScope(function(Builder $builder){
            $builder->where(
                'type',
                'NOT LIKE',
                config('broadcasting.status_settings.namespace').'%'
            );
        });
    }

    public function getGroupId(): ?int
    {
        return $this->channel_group_id;
    }

    public function channelGroup()
    {
        return $this->belongsTo(ChannelGroup::class);
    }

    public function channelPermissionable(): BelongsTo
    {
        return $this->belongsTo(ChannelPermissionable::class);
    }

    public function group(): BelongsTo
    {
        return $this->channelGroup();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function parent()
    {
        return $this->belongsTo(Channel::class);
    }

    public function children()
    {
        return $this->hasMany(Channel::class, 'parent_id');
    }

    public function parentListingPivot(): MorphOne
    {
        return $this->morphOne(CompanyChannelListing::class, 'companyable');
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function playouts()
    {
        return $this->hasMany(ScheduleChannelPlayout::class, 'playout_channel_id');
    }

    public function playoutHistory()
    {
        return $this->hasMany(PlayoutHistory::class);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDefaultOfType($query, $type)
    {
        $trimmedType = ltrim($type, '/');

        return $query
            ->where('is_default', true)
            ->whereIn('type', [$trimmedType, '/'.$trimmedType]);
    }
}
