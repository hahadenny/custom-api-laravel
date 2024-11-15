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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Template> $templates
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Template> $listingTemplates
 * @property-read \App\Models\TemplateGroup|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\TemplateGroup> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\TemplateGroup> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\TemplateGroup> $descendants
 */
class TemplateGroup extends Model implements GroupInterface
{
    use BelongsToCompany, HasFactory, ListingGroupTrait, ListingSortableTrait, NodeTrait, SoftDeletes, SetsRelationAlias;

    protected const RELATION_MAP = ['templates' => 'items', 'items' => 'templates'];

    protected $fillable = [
        'name',
        'parent_id',
    ];

    protected $hidden = [
        'deleted_at',
        'pivot',
        'parentListingPivot',
        'listingTemplates',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'sort_order',
    ];


    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    public function items(): HasMany
    {
        return $this->templates();
    }

    public function listingTemplates()
    {
        return $this->listingItems(Template::class, 'companyable', 'company_template_listings')
            ->withPivot(['company_id']);
    }

    // group children (?)
    public function listingTemplateGroups()
    {
        return $this->listingItems(TemplateGroup::class, 'companyable', 'company_template_listings')
                    ->withPivot(['company_id']);
    }

    public function listingTemplatesPivotWithTrashed()
    {
        return $this->listingItemsWithPivotTrashed(Template::class, 'companyable', 'company_template_listings')
                    ->withPivot(['company_id'])
                    ->withTrashed();
    }

    public function listingTemplatesPivotOnlyTrashed()
    {
        return $this->listingItemsWithPivotTrashed(Template::class, 'companyable', 'company_template_listings')
                    ->withPivot(['company_id'])
                    ->wherePivotNotNull('deleted_at');
    }

    public function listingTemplateGroupsPivotWithTrashed()
    {
        return $this->listingItemsWithPivotTrashed(TemplateGroup::class, 'companyable', 'company_template_listings')
                    ->withPivot(['company_id'])
                    ->withTrashed();
    }

    public function parentListingPivot(): MorphOne
    {
        return $this->morphOne(CompanyTemplateListing::class, 'companyable');
    }

    public function parentListingPivotWithTrashed(): MorphOne
    {
        return $this->morphOne(CompanyTemplateListing::class, 'companyable')->withTrashed();
    }

    public function parentListingPivotOnlyTrashed(): MorphOne
    {
        return $this->morphOne(CompanyTemplateListing::class, 'companyable')->onlyTrashed();
    }
}
