<?php

namespace App\Contracts\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property-read int|null $sort_order
 * @property int|null $parent_id
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model> $items
 * @property-read \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model> $descendants
 * @property \Illuminate\Support\Collection<\App\Contracts\Models\GroupInterface|\App\Contracts\Models\ItemInterface> $components
 * @property-read \App\Contracts\Models\ListingPivotInterface|\Illuminate\Database\Eloquent\Model|null $parentListingPivot
 * @mixin \Illuminate\Database\Eloquent\Model
 * @mixin \Illuminate\Database\Eloquent\SoftDeletes
 */
interface GroupInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company();

    public function scopeWithinCompany($query, Company $company);

    public function items(): HasMany|BelongsToMany;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent();

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children();

    /**
     * @return \Kalnoy\Nestedset\AncestorsRelation
     */
    public function ancestors();

    /**
     * @return \Kalnoy\Nestedset\DescendantsRelation
     */
    public function descendants();

    /**
     * @return \Kalnoy\Nestedset\QueryBuilder
     */
    public function newQuery();
}
