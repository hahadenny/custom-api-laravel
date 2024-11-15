<?php

namespace App\Models;

use App\Traits\Models\ListingParentTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $cluster_id
 * @property string $name
 * @property string|null $description
 * @property string $ue_url
 * @property bool $create_ue_server
 * @property bool $is_active
 * @property string|null $api_key
 * @property array|null $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UserGroup> $userGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Workspace> $workspaces
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Project> $projects
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Playlist> $playlists
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PlaylistGroup> $playlistGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Template> $templates
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\TemplateGroup> $templateGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Template> $listingTemplates
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\TemplateGroup> $listingTemplateGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Channel> $channels
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ChannelGroup> $channelGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Channel> $listingChannels
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ChannelGroup> $listingChannelGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page> $pages
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page> $playlistPages
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page> $withoutPlaylistPages
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Field> $fields
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\PageGroup> $pageGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UePresetAsset> $uePresetAssets
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UePresetGroup> $uePresetGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UePreset> $uePresets
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Workflow> $workflows
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\MediaMeta> $mediaMetas
 * @property-read \App\Models\Cluster $cluster
 * @method Builder active()
 */
class Company extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia, ListingParentTrait;

    const MEDIA_COLLECTION_NAME = 'default';

    public $registerMediaConversionsUsingModelInstance = true;

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'json',
    ];

    protected $fillable = [
        'name',
        'description',
        'ue_url',
        'is_active',
        'cluster_id',
        'settings',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'api_key',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $company) {
            $company->generateAndSetApiKey();
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function userGroups()
    {
        return $this->hasMany(UserGroup::class);
    }

    public function workspaces()
    {
        return $this->hasMany(Workspace::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    public function playlistGroups()
    {
        return $this->hasMany(PlaylistGroup::class);
    }

    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    public function templateGroups()
    {
        return $this->hasMany(TemplateGroup::class);
    }

    public function channelLayers()
    {
        return $this->hasMany(ChannelLayer::class);
    }

    public function listingTemplates($withTrashed=false)
    {
        return $this->listingChildren(
            Template::class,
            'companyable',
            'company_template_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function listingTemplatesPivotWithTrashed($withTrashed=false)
    {
        return $this->listingChildrenWithPivotTrashed(
            Template::class,
            'companyable',
            'company_template_listings',
            ['id', 'group_id', 'sort_order']
        )->withTrashed();
    }

    public function listingTemplatesPivotOnlyTrashed($withTrashed=false)
    {
        return $this->listingChildrenWithPivotTrashed(
            Template::class,
            'companyable',
            'company_template_listings',
            ['id', 'group_id', 'sort_order']
        )->wherePivotNotNull('deleted_at');
    }

    public function listingTemplateGroups()
    {
        return $this->listingChildren(
            TemplateGroup::class,
            'companyable',
            'company_template_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function listingTemplateGroupsPivotWithTrashed()
    {
        return $this->listingChildrenWithPivotTrashed(
            TemplateGroup::class,
            'companyable',
            'company_template_listings',
            ['id', 'group_id', 'sort_order']
        )->withTrashed();
    }

    public function listingTemplateGroupsPivotOnlyTrashed()
    {
        return $this->listingChildrenWithPivotTrashed(
            TemplateGroup::class,
            'companyable',
            'company_template_listings',
            ['id', 'group_id', 'sort_order']
        )->wherePivotNotNull('deleted_at');
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    public function channelGroups()
    {
        return $this->hasMany(ChannelGroup::class);
    }

    public function listingChannels()
    {
        return $this->listingChildren(
            Channel::class,
            'companyable',
            'company_channel_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function listingChannelGroups()
    {
        return $this->listingChildren(
            ChannelGroup::class,
            'companyable',
            'company_channel_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function playlistPages()
    {
        return $this->listingChildren(
            Page::class,
            'playlistable',
            'playlist_listings',
            ['id', 'group_id', 'sort_order']
        );
    }

    public function withoutPlaylistPages()
    {
        return $this->playlistPages()->wherePivotNull('playlist_id');
    }

    public function fields()
    {
        return $this->hasMany(Field::class);
    }

    public function pageGroups()
    {
        return $this->hasMany(PageGroup::class);
    }

    public function uePresetAssets()
    {
        return $this->hasMany(UePresetAsset::class);
    }

    public function uePresetGroups()
    {
        return $this->hasMany(UePresetGroup::class);
    }

    public function uePresets()
    {
        return $this->hasMany(UePreset::class);
    }

    public function companyIntegrations()
    {
        return $this->hasMany(CompanyIntegrations::class);
    }

    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function mediaMetas() : HasMany
    {
        return $this->hasMany(MediaMeta::class);
    }

    public function tags() : HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function generateAndSetApiKey(): void
    {
        $this->api_key = static::generateApiKey();
    }

    public static function generateApiKey(): string
    {
        $apiKey = '';

        for ($i = 0; $i < 100; ++$i) {
            $apiKey = Str::random(64);
            $exists = static::apiKeyExists($apiKey);

            if (! $exists) {
                break;
            }
        }

        if ($exists) {
            throw new \Exception('Failed to generate a unique key.');
        }

        return $apiKey;
    }

    public static function apiKeyExists(string $apiKey): bool
    {
        return static::query()->withTrashed()->where('api_key', $apiKey)->exists();
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->extractVideoFrameAtSecond(1)
            ->width(640)
            ->nonQueued();
    }

    public function scopeActive($query) : Builder
    {
        return $query->where(['is_active' => true]);
    }

}
