<?php

namespace App\Api\V1\Controllers\Schedule;

use App\Api\V1\Resources\Schedule\ScheduleSetResource;
use App\Http\Controllers\Controller;
use App\Models\Schedule\ScheduleSet;
use App\Models\User;
use App\Services\Schedule\ScheduleSetService;

/**
 * Methods pertaining to the active Schedule Set of a User
 *
 * @group Schedule Set
 */
class UserScheduleSetController extends Controller
{
    public function __construct()
    {
        // $this->middleware(['can:view,scheduleSet']);

        //# authorizeResource(resource model name to authorize, route param name for the model instance)
        //# controller methods will be mapped to their corresponding policy method
        //# e.g., show --> view, edit --> update
        //# https://laravel.com/docs/8.x/authorization#authorizing-resource-controllers
        // $this->authorizeResource(ScheduleSet::class, 'user');
    }

    /**
     * Update the active ScheduleSet for a given User
     *
     * @return ScheduleSetResource
     */
    public function update(User $user, ScheduleSet $scheduleSet, ScheduleSetService $service)
    {
        return new ScheduleSetResource($service->updateActive($user, $scheduleSet));
    }
}
