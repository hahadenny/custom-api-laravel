<?php

namespace App\Contracts\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entities that belong to a company and can be grouped
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property-read int|null $sort_order
 * @property-read \App\Models\Company $company
 * @property-read \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model|null $group
 * @property-read \App\Contracts\Models\ListingPivotInterface|\Illuminate\Database\Eloquent\Model|null $parentListingPivot
 * @mixin \Illuminate\Database\Eloquent\Model
 */
interface ItemInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company();

    public function scopeWithinCompany($query, Company $company);

    public function group(): BelongsTo;

    public function getGroupId(): ?int;
}
