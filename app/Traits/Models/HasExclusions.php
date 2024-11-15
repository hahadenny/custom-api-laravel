<?php

namespace App\Traits\Models;

use App\Models\Scopes\IsNotExclusionScope;

/**
 * For models that have a boolean is_exclusion column
 * and utilize IsNotExclusionScope
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 * @method \Illuminate\Database\Eloquent\Builder withExclusions()
 * @method \Illuminate\Database\Eloquent\Builder onlyExclusions()
 */
trait HasExclusions
{
    /**
     * Query including the instances that are exclusions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithExclusions($query)
    {
        return $query->withoutGlobalScope(IsNotExclusionScope::class);
    }

    /**
     * Query only the instances that are exclusions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyExclusions($query)
    {
        return $query->withoutGlobalScope(IsNotExclusionScope::class)
                     ->where('is_exclusion', '=', 1);
    }
}
