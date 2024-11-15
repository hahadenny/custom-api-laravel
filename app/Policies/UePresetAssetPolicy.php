<?php

namespace App\Policies;

use App\Models\UePresetAsset;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UePresetAssetPolicy
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
     * @param  \App\Models\UePresetAsset  $uePresetAsset
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, UePresetAsset $uePresetAsset)
    {
        return $user->isInSameCompany($uePresetAsset);
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
     * @param  \App\Models\UePresetAsset  $uePresetAsset
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, UePresetAsset $uePresetAsset)
    {
        return $user->isInSameCompany($uePresetAsset);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UePresetAsset  $uePresetAsset
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, UePresetAsset $uePresetAsset)
    {
        return $user->isInSameCompany($uePresetAsset);
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
     * @param  \App\Models\UePresetAsset  $uePresetAsset
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, UePresetAsset $uePresetAsset)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UePresetAsset  $uePresetAsset
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, UePresetAsset $uePresetAsset)
    {
        //
    }
}
