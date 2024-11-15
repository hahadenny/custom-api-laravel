<?php

namespace App\Policies\Schedule;

use App\Models\Schedule\ScheduleRule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScheduleRulePolicy
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
     * @param \App\Models\User $user
     * @param ScheduleRule     $ruleset
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ScheduleRule $ruleset)
    {
        return $user->isInSameCompany($ruleset->schedule->scheduleable->channel);
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
     * @param \App\Models\User $user
     * @param ScheduleRule     $ruleset
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ScheduleRule $ruleset)
    {
        return $user->isInSameCompany($ruleset->schedule->scheduleable->channel);
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
     * Determine whether the user can duplicate the models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function batchDuplicate(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param \App\Models\User $user
     * @param ScheduleRule     $ruleset
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ScheduleRule $ruleset)
    {
        return $user->isInSameCompany($ruleset->schedule->scheduleable->channel);
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
     * @param \App\Models\User $user
     * @param ScheduleRule     $ruleset
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ScheduleRule $ruleset)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param \App\Models\User $user
     * @param ScheduleRule     $ruleset
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ScheduleRule $ruleset)
    {
        //
    }
}
