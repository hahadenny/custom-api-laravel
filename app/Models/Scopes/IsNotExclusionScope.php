<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Get non-exclusion models only
 */
class IsNotExclusionScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $builder->where('is_exclusion', '<>', 1);
    }
}
