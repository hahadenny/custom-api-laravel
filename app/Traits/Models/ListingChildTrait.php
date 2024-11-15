<?php

namespace App\Traits\Models;

/**
 * Relation methods for Models that can be a polymorphic child (ex, Page, Playlist...)
 *
 * Inverse of the morphedByMany relation from ListingParentTrait
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ListingChildTrait
{
    protected function listingParent(string $related, string $name, string $table, array $with_pivot=['id', 'sort_order'], $foreign_pivot_key = null, $related_pivot_key = null,)
    {
        return $this->listingParentWithPivotTrashed($related, $name, $table, $with_pivot, $foreign_pivot_key, $related_pivot_key)
            ->wherePivotNull('deleted_at');
    }

    protected function listingParentWithPivotTrashed(string $related, string $name, string $table, array $with_pivot=['id', 'sort_order'], $foreign_pivot_key = null, $related_pivot_key = null,)
    {
        return $this->morphToMany($related, $name, $table, $foreign_pivot_key, $related_pivot_key)
            ->withPivot($with_pivot)
            ->withTimestamps();
    }
}
