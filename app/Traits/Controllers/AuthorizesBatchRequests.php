<?php

namespace App\Traits\Controllers;

trait AuthorizesBatchRequests
{
    /**
     * Get the map of resource methods to ability names.
     *
     * @return array
     * @see \Illuminate\Foundation\Auth\Access\AuthorizesRequests::resourceAbilityMap()
     */
    protected function resourceAbilityMap()
    {
        $map = [];

        if (method_exists(parent::class, 'resourceAbilityMap')) {
            $map = parent::resourceAbilityMap();
        }

        return array_merge($map, [
            'batchUpdate' => 'batchUpdate',
            'batchDuplicate' => 'batchDuplicate',
            'batchUngroup' => 'batchUngroup',
            'batchDestroy' => 'batchDelete',
            'batchRestore' => 'batchRestore',
        ]);
    }

    /**
     * Get the list of resource methods which do not have model parameters.
     *
     * @return array
     * @see \Illuminate\Foundation\Auth\Access\AuthorizesRequests::resourceMethodsWithoutModels()
     */
    protected function resourceMethodsWithoutModels()
    {
        $methods = [];

        if (method_exists(parent::class, 'resourceMethodsWithoutModels')) {
            $methods = parent::resourceMethodsWithoutModels();
        }

        return array_merge($methods, [
            'batchUpdate',
            'batchDuplicate',
            'batchUngroup',
            'batchDestroy',
            'batchRestore',
        ]);
    }
}
