<?php

namespace App\Traits\Models;

/**
 * Relation methods for Listing Models that can contain items (ex, PageGroup, PlaylistGroup...)
 * @see \App\Traits\Models\ListingParentTrait for potential duplicate
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ListingGroupTrait
{
    protected function listingItems(string $related, string $name, string $table)
    {
        return $this->listingItemsWithPivotTrashed($related, $name, $table)
            ->wherePivotNull('deleted_at');
    }

    protected function listingItemsWithPivotTrashed(string $related, string $name, string $table)
    {
        return $this->morphedByMany($related, $name, $table, 'group_id')
            ->withPivot(['id', 'group_id', 'sort_order'])
            ->withTimestamps();
    }
}
