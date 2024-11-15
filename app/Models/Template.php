<?php

namespace App\Models;

use App\Casts\ScheduleDurationCast;
use App\Contracts\Models\ItemInterface;
use App\Models\Schedule\ScheduleListing;
use App\Traits\HasTags;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\ListingSortableTrait;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                                                             $id
 * @property int                                                             $company_id
 * @property int|null                                                        $template_group_id
 * @property int|null                                                        $ue_preset_asset_id
 * @property string|null                                                     $preset
 * @property string                                                          $name
 * @property \App\Enums\TemplateType                                         $type
 * @property string|null                                                     $engine
 * @property string|null                                                     $color
 * @property array                                                           $data
 * @property int|null                                                        $default_duration
 * @property int                                                             $created_by
 * @property boolean                                                         $is_active
 * @property \Illuminate\Support\Carbon|null                                 $created_at
 * @property \Illuminate\Support\Carbon|null                                 $updated_at
 * @property \Illuminate\Support\Carbon|null                                 $deleted_at
 * @property-read \App\Models\Company                                        $company
 * @property-read \App\Models\TemplateGroup|null                             $group
 * @property-read \App\Models\User                                           $createdBy
 * @property-read \App\Models\UePresetAsset|null                             $uePresetAsset
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Page> $pages
 */
class Template extends Model implements ItemInterface
{
    use BelongsToCompany, HasFactory, ListingSortableTrait, SoftDeletes, SetsRelationAlias, HasTags;

    const PRESET_D3 = 'd3';

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['templateGroup' => 'group'];

    public const FILE_COMPONENT          = 'file';
    public const MEDIA_MANAGER_COMPONENT = 'media_manager';

    // columns to query when omitting `data` column
    public const COLUMNS = ['templates.id', 'templates.name', 'templates.company_id', 'templates.template_group_id', 'templates.preset', 'templates.type', 'templates.created_by', 'templates.updated_at', 'templates.created_at', 'templates.deleted_at','templates.is_active', 'templates.engine', 'templates.color'];

    protected $fillable = [
        'template_group_id',
        'ue_preset_asset_id',
        'preset',
        'name',
        'type',
        'engine',
        'color',
        'data',
        'default_duration',
        'is_active'
    ];

    protected $hidden = [
        'company_id',
        'deleted_at',
        'created_at',
        'pivot',
        'parentListingPivot',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'json',
        'default_duration' => ScheduleDurationCast::class,
        'is_active' => 'boolean'
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

        static::deleting(function(self $template) {
            foreach($template->pages()->get() as $page) {
                /** @var \App\Models\Page $page */
                $page->delete();
            }
        });

        static::saving(function (self $template) {
            if (is_null($template->default_duration)) {
                $template->default_duration = ScheduleListing::DEFAULT_DURATION;
            }
        });
    }

    public function getGroupId() : ?int
    {
        return $this->template_group_id;
    }

    public function templateGroup()
    {
        return $this->belongsTo(TemplateGroup::class);
    }

    public function group() : BelongsTo
    {
        return $this->templateGroup();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function uePresetAsset()
    {
        return $this->belongsTo(UePresetAsset::class);
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function parentListingPivot() : MorphOne
    {
        return $this->morphOne(CompanyTemplateListing::class, 'companyable');
    }

    public function parentListingPivotOnlyTrashed() : MorphOne
    {
        return $this->morphOne(CompanyTemplateListing::class, 'companyable')->onlyTrashed();
    }

    public function parentListingPivotWithTrashed() : MorphOne
    {
        return $this->morphOne(CompanyTemplateListing::class, 'companyable')->withTrashed();
    }

    /**
     * @param array|string $type
     *
     * @return \Illuminate\Support\Collection
     */
    public function findNodesByType($type) : \Illuminate\Support\Collection
    {
        $schema = $this->data;
        if(empty($schema)) {
            return collect();
        }
        $types = is_array($type) ? $type : [$type];
        $result = [];
        $this->propsFromSchema($schema, function($node) use (&$result, $types) {
            if(isset($node['type']) && in_array($node['type'], $types)) {
                $result[] = $node;
            }
        });
        return collect($result);
    }

    public function findNodesByKeyBeginWith($name) : \Illuminate\Support\Collection
    {
        $schema = $this->data;
        if(empty($schema)) {
            return collect();
        }
        $result = [];
        $this->propsFromSchema($schema, function($node) use (&$result, $name) {
            if(isset($node['key']) && str_starts_with($node['key'], $name)) {
                $result[] = $node;
            }
        });
        return collect($result);
    }

    public function propsFromSchema($schema, $callback)
    {
        $callback($schema);
        if( !empty($schema['components'])) {
            foreach($schema['components'] as $component) {
                $this->propsFromSchema($component, $callback);
            }
        }
        if( !empty($schema['rows']) && is_array($schema['rows'])) {
            foreach($schema['rows'] as $row) {
                foreach($row as $col) {
                    $this->propsFromSchema($col, $callback);
                }
            }
        }
        if( !empty($schema['columns']) && is_array($schema['columns'])) {
            foreach($schema['columns'] as $column) {
                $this->propsFromSchema($column, $callback);
            }
        }
    }

    public function scopeActive(Builder $builder)
    {
        return $builder->where(['is_active' => 1]);
    }

    public function scopeInactive(Builder $builder)
    {
        return $builder->where(['is_active' => 0]);
    }
}
