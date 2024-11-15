<?php

namespace App\Models;

use App\Casts\ScheduleDurationCast;
use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ScheduleableInterface;
use App\Models\Schedule\ScheduleListing;
use App\Services\Schedule\ScheduleListingService;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingChildTrait;
use App\Traits\Models\Schedule\IsScheduleable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property int                                                                        $id
 * @property int                                                                        $company_id
 * @property int|null                                                                   $template_id
 * @property int                                                                        $channel_id
 * @property string|null                                                                $channel_entity_type
 * @property int|null                                                                   $channel_entity_id
 * @property int|null                                                                   $original_id
 * @property string                                                                     $name
 * @property string|null                                                                $description
 * @property array                                                                      $data
 * @property int|null                                                                   $duration
 * @property int                                                                        $is_live
 * @property bool                                                                       $has_media
 * @property int                                                                        $page_number
 * @property int                                                                        $created_by
 * @property string|null                                                                $preview_url
 * @property string|null                                                                $color
 * @property \Illuminate\Support\Carbon|null                                            $created_at
 * @property \Illuminate\Support\Carbon|null                                            $updated_at
 * @property \Illuminate\Support\Carbon|null                                            $deleted_at
 * @property string|null                                                                $subchannel // DEPRECATED -- Avalanche
 *           channel
 * @property-read int|null                                                              $playlist_id
 * @property-read int|null                                                              $page_group_id
 * @property-read int|null                                                              $sort_order
 * @property-read string|null                                                           $edited_fields
 * @property-read \App\Models\Company                                                   $company
 * @property-read \App\Models\PageGroup|null                                            $group
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Playlist>        $playlists
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PageGroup>       $groupsViaPlaylistListing
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PageGroup>
 *                $groupsViaPlaylistListingWithPivotTrashed
 * @property-read \App\Models\Template|null                                             $template
 * @property-read \App\Models\Channel|null                                              $channel
 * @property-read \App\Models\Channel|\App\Models\ChannelGroup|null                     $channelEntity
 * @property-read \App\Models\Page|null                                                 $original
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page>            $references
 * @property-read \App\Models\User                                                      $createdBy
 * @property-read \App\Models\ChangeLog|null                                            $changeLog
 * @property-read \App\Models\PlaylistListing|null                                      $parentListingPivot
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PlaylistListing> $playlistListingPivots
 */
class Page extends Model implements ItemInterface, ScheduleableInterface
{
    use BelongsToCompany, HasFactory, SoftDeletes, ListingChildTrait, IsScheduleable;

    protected $fillable = [
        'template_id',
        'channel_id',
        'channel_entity_type',
        'channel_entity_id',
        'name',
        'description',
        'data',
        'is_live',
        'page_number',
        'color',
        'edited_fields',
        'subchannel',
        'duration',
    ];

    protected $hidden = [
        'deleted_at',
        'pivot',
        'playlistListingPivots',
        // 'scheduleListingPivots',
        'groupsViaPlaylistListing',
        'groupsViaPlaylistListingWithPivotTrashed',
    ];

    protected $casts = [
        'is_live'   => 'integer',
        'has_media' => 'boolean',
        'data'      => 'array',
        'duration'  => ScheduleDurationCast::class,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'playlist_id',
        'page_group_id',
        'sort_order',
    ];

    protected ?Playlist $parentModel = null;

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $page) {
            /**
             * If the page live status was just updated by PlaylistView's "Send Now" action,
             * set all other pages for this company to NOT live.
             * NOTE: This status is not related to the Scheduler
             */
            if ($page->isDirty('is_live') && $page->is_live) {
                $page->company->pages()
                              ->where(['channel_id' => $page->channel_id])
                              ->whereKeyNot($page)
                              ->update(['is_live' => 0]);
            }
            if ($page->isDirty('data')) {
                $page->has_media = count($page->getMedia()) > 0;
                if (!$page->has_media)
                    $page->has_media = count($page->getD3SharedMedia()) > 0;
            }

            if ($page->isDirty('channel_id') && $page->parentModel instanceof Playlist) {
                // when the channel is changed & Page has a playlist, we need to see if the page
                // should be added or removed from the Scheduler
                if ($page->getOriginal('channel_id') === null && isset($page->parentListingPivot)) {
                    ScheduleListingService::addPageToParent($page);
                }
                /*if($page->channel_id === null){
                    // page unassigned from any channel, might not even be possible via UI
                }*/
            }
        });

        static::deleting(function (self $page) {
            foreach ($page->playlistListingPivots()->get() as $parentListingPivot) {
                /** @var \Illuminate\Database\Eloquent\Model $parentListingPivot */
                $parentListingPivot->delete();
            }

            foreach ($page->references()->get() as $reference) {
                /** @var \App\Models\Page $reference */
                $reference->delete();
            }

            // NOTE: scheduleListingPivot will delete via ListingObserver
        });
    }

    public function getParentModel() : ?Playlist
    {
        return $this->parentModel;
    }

    public function setParentModel(?Playlist $parentModel) : Page
    {
        $this->parentModel = $parentModel;
        return $this;
    }

    public function getParentListingPivotAttribute()
    {
        return $this->getParentListingPivot();
    }

    public function getPlaylistIdAttribute()
    {
        return $this->getParentListingPivot()?->playlist_id;
    }

    public function getPageGroupIdAttribute()
    {
        return $this->getParentListingPivot()?->group_id;
    }

    public function getGroupId() : ?int
    {
        return $this->getParentListingPivot()?->group_id;
    }

    public function getSortOrderAttribute()
    {
        return $this->getParentListingPivot()?->sort_order;
    }

    protected function getParentListingPivot() : ?PlaylistListing
    {
        return $this->playlistListingPivots
            ->where('playlist_id', $this->parentModel?->id)
            ->first();
    }

    public function getGroupAttribute()
    {
        return $this->getGroup($this->parentModel);
    }

    public function getGroup(?Playlist $parentModel) : ?PageGroup
    {
        return $this->groupsViaPlaylistListing
            ->where('pivot.playlist_id', $parentModel?->id)
            ->first();
    }

    public function groupsViaPlaylistListing()
    {
        return $this->groupsViaPlaylistListingWithPivotTrashed()
                    ->wherePivotNull('deleted_at');
    }

    public function groupsViaPlaylistListingWithPivotTrashed()
    {
        return $this->listingParent(
            PageGroup::class,
            'playlistable',
            'playlist_listings',
            ['playlist_id', 'group_id', 'sort_order'],
            null,
            'group_id'
        );
    }

    public function group() : never
    {
        throw new LogicException('The "group" relationship does not apply to page.');
    }

    public function playlists()
    {
        return $this->listingParent(Playlist::class, 'playlistable', 'playlist_listings');
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function channelEntity()
    {
        return $this->morphTo();
    }

    public function original()
    {
        return $this->belongsTo(Page::class);
    }

    public function references()
    {
        return $this->hasMany(Page::class, 'original_id');
    }

    public function createdBy() : BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function changeLog()
    {
        return $this->morphOne(ChangeLog::class, 'changeable');
    }

    public function playlistListingPivots()
    {
        return $this->morphMany(PlaylistListing::class, 'playlistable');
    }

    public function getMedia() : array
    {
        $template = $this->template;
        if ( !$template) {
            return [];
        }
        $fields = $template->findNodesByType([Template::MEDIA_MANAGER_COMPONENT])
                           ->pluck('defaultValue', 'key');
        if ($fields->count() === 0) {
            return [];
        }
        $data = $this->data;
        $res = [];
        foreach ($fields as $key => $val) {
            $value = empty($data[$key]) ? $val : $data[$key];
            if (!$value) {
                continue;
            }
            $res[$value['id']] = $value;
        }
        return array_values($res);
    }

    public function getD3SharedMedia() : array
    {
        $template = $this->template;
        if ( !$template) {
            return [];
        }
        $d3_fields = $template->findNodesByKeyBeginWith('share_file_')
                           ->pluck('defaultValue', 'key');
        if ($d3_fields->count() === 0) {
            return [];
        }
        $data = $this->data;
        $res = [];
        foreach ($d3_fields as $key => $val) {
            $value = empty($data[$key]) ? $val : $data[$key];
            if (!$value) {
                continue;
            }
            $res[] = $value;
        }
        return array_values($res);
    }

    public function getDuration() : int
    {
        // if no duration set, fall back to template duration, then scheduler default duration
        return $this->duration ?? $this->template->default_duration ?? ScheduleListing::DEFAULT_DURATION;
    }
}
