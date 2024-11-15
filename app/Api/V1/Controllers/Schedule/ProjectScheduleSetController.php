<?php

namespace App\Api\V1\Controllers\Schedule;

use App\Api\V1\Requests\Schedule\ScheduleSet\UpsertRequest;
use App\Api\V1\Resources\Schedule\ScheduleSetResource;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Schedule\ScheduleSet;
use App\Services\Schedule\ScheduleSetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Methods pertaining to the Schedule Sets within a Project
 *
 * @group Schedule Set
 */
class ProjectScheduleSetController extends Controller
{
    public function __construct()
    {
        // $this->middleware(['can:view,scheduleSet']);

        //# authorizeResource(resource model name to authorize, route param name for the model instance)
        //# controller methods will be mapped to their corresponding policy method
        //# e.g., show --> view, edit --> update
        //# https://laravel.com/docs/8.x/authorization#authorizing-resource-controllers
        // $this->authorizeResource(ScheduleSet::class, 'project');
    }

    /**
     * Display a listing of schedule sets for a specified project
     *
     * @param Project            $project
     * @param ScheduleSetService $service
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Project $project, ScheduleSetService $service)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return ScheduleSetResource::collection($service->listing($project, $authUser));
    }

    /**
     * Update the specified schedule set of a project
     *
     * @return ScheduleSetResource
     */
    public function update(UpsertRequest $request, Project $project, ScheduleSet $scheduleSet, ScheduleSetService $service)
    {
        return new ScheduleSetResource($service->update($project, $scheduleSet, $request->validated()));
    }

    /**
     * Create a new schedule set for a project
     *
     * @return ScheduleSetResource
     */
    public function store(UpsertRequest $request, Project $project, ScheduleSetService $service)
    {
        return new ScheduleSetResource($service->store($project, $request->validated()));
    }
}
