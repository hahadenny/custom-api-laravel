<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FilePolicy
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
     * @param  Media  $file
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Media $file)
    {
        return $this->checkFile($user, $file);
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
     * @param  Media  $media
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Media $file)
    {
        return $this->checkFile($user, $file);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  Media  $file
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Media $file)
    {
        return $this->checkFile($user, $file);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  Media  $file
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Media $file)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  Media  $file
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Media $file)
    {
        //
    }

    protected function checkFile(User $user, Media $file): bool
    {
        return $user->company->media()->where('id', $file->id)->exists();
    }
}
