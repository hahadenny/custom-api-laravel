<?php

namespace App\Policies;

use App\Models\UePresetGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UePresetGroupPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     *
     * @param  \App\Models\User  $user
     * @param  string  $ability
     * @return void|bool
     */
    public function before(User $user, string $ability)
    {
        if ($user->isSuperAdmin()) {
            return false;
        }
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UePresetGroup  $uePresetGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, UePresetGroup $uePresetGroup)
    {
        return $user->isInSameCompany($uePresetGroup);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UePresetGroup  $uePresetGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, UePresetGroup $uePresetGroup)
    {
        return $user->isInSameCompany($uePresetGroup);
    }

    /**
     * Determine whether the user can update the models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function batchUpdate(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UePresetGroup  $uePresetGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, UePresetGroup $uePresetGroup)
    {
        return $user->isInSameCompany($uePresetGroup);
    }

    /**
     * Determine whether the user can delete the models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function batchDelete(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UePresetGroup  $uePresetGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, UePresetGroup $uePresetGroup)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UePresetGroup  $uePresetGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, UePresetGroup $uePresetGroup)
    {
        //
    }
}
