<?php

namespace App\Traits\Models;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Methods for models that can morph to a ListingPivotInterface
 *
 * @property-read int|null $sort_order
 * @property-read \App\Contracts\Models\ListingPivotInterface|\Illuminate\Database\Eloquent\Model|null $parentListingPivot
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ListingSortableTrait
{
    public static function bootListingSortableTrait(): void
    {
        static::deleting(function (self $model) {
            // soft delete imitation of ON DELETE CASCADE
            foreach ($model->parentListingPivot()->get() as $parentListingPivot) {
                /** @var \Illuminate\Database\Eloquent\Model $parentListingPivot */
                $parentListingPivot->delete();
            }
        });
    }

    abstract function parentListingPivot(): Relation;

    public function getSortOrderAttribute()
    {
        return $this->parentListingPivot?->sort_order;
    }
}
