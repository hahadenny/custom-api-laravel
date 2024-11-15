<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowPolicy
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
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Workflow $workflow)
    {
        return $user->isInSameCompany($workflow);
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
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Workflow $workflow)
    {
        return $user->isInSameCompany($workflow);
    }

    /**
     * Determine whether the user can run the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function run(User $user, Workflow $workflow)
    {
        return $user->isInSameCompany($workflow);
    }

    /**
     * Determine whether the user can run the filter steps.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function runFilterSteps(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can create model logs.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function workflowRunLogStore(User $user, Workflow $workflow)
    {
        return $user->isInSameCompany($workflow);
    }

    /**
     * Determine whether the user can revert the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function revert(User $user, Workflow $workflow)
    {
        return $user->isInSameCompany($workflow);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Workflow $workflow)
    {
        return $user->isInSameCompany($workflow);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Workflow $workflow)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Workflow $workflow)
    {
        //
    }
}
