<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Project\DuplicateRequest;
use App\Api\V1\Requests\Project\StoreRequest;
use App\Api\V1\Requests\Project\UpdateRequest;
use App\Api\V1\Resources\ProjectListingResource;
use App\Api\V1\Resources\ProjectResource;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Project::class, 'project');
    }

    /**
     * Display a listing of projects.
     *
     * @group Project
     *
     * @param  \App\Services\ProjectService  $projectService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(ProjectService $projectService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return ProjectListingResource::collection($projectService->listing($authUser));
    }

    /**
     * Store a newly created project.
     *
     * @group Project
     *
     * @param  \App\Api\V1\Requests\Project\StoreRequest  $request
     * @param  \App\Services\ProjectService  $projectService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, ProjectService $projectService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new ProjectResource($projectService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified project.
     *
     * @group Project
     *
     * @param  \App\Models\Project  $project
     * @param  \App\Services\ProjectService  $projectService
     * @return \App\Api\V1\Resources\ProjectResource
     */
    public function show(Project $project, ProjectService $projectService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return new ProjectResource($projectService->show($project, $authUser));
    }

    /**
     * Update the specified project.
     *
     * @group Project
     *
     * @param  \App\Api\V1\Requests\Project\UpdateRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\ProjectService  $projectService
     * @return \App\Api\V1\Resources\ProjectResource
     */
    public function update(UpdateRequest $request, Project $project, ProjectService $projectService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return new ProjectResource($projectService->update($project, $authUser, $request->validated()));
    }

    /**
     * Duplicate the specified project.
     *
     * @group Project
     *
     * @param  \App\Api\V1\Requests\Project\DuplicateRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\ProjectService  $projectService
     * @return \App\Api\V1\Resources\ProjectResource
     */
    public function duplicate(DuplicateRequest $request, Project $project, ProjectService $projectService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $this->authorize('duplicate', $project);

        return new ProjectResource($projectService->duplicate($project, $authUser, $request->validated()));
    }

    /**
     * Remove the specified project.
     *
     * @group Project
     *
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Project $project)
    {
        $project->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
