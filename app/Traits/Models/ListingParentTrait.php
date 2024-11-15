<?php

namespace App\Traits\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Relation methods for Models that can be a parent (ex, Playlist, Layer...)
 * Inverse of the morphToMany relation in ListingChildTrait
 *
 * Formerly `ComponentListingTrait` (now without defaulting to groups)
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ListingParentTrait
{
    protected function listingChildren(string $related, string $name, string $table, array $with_pivot=['id', 'sort_order'], $foreign_pivot_key = null, $related_pivot_key = null,) : MorphToMany
    {
        return $this->listingChildrenWithPivotTrashed($related, $name, $table, $with_pivot, $foreign_pivot_key, $related_pivot_key)
            ->wherePivotNull('deleted_at');
    }

    protected function listingChildrenWithPivotTrashed(string $related, string $name, string $table, array $with_pivot=['id', 'sort_order'], $foreign_pivot_key = null, $related_pivot_key = null,)
    {
        return $this->morphedByMany($related, $name, $table, $foreign_pivot_key, $related_pivot_key)
            ->withPivot($with_pivot)
            ->withTimestamps();
    }
}
