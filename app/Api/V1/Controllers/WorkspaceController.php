<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Workspace\StoreRequest;
use App\Api\V1\Requests\Workspace\UpdateRequest;
use App\Api\V1\Resources\WorkspaceResource;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Workspace::class, 'workspace');
    }

    /**
     * Display a listing of workspaces.
     *
     * @group Workspace
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $workspaces = $authUser->workspaces()->withinSameCompany($authUser)->orderByDesc('id')->paginate();

        return WorkspaceResource::collection($workspaces);
    }

    /**
     * Store a newly created workspace.
     *
     * @group Workspace
     *
     * @param  \App\Api\V1\Requests\Workspace\StoreRequest  $request
     * @param  \App\Services\WorkspaceService  $workspaceService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, WorkspaceService $workspaceService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new WorkspaceResource($workspaceService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified workspace.
     *
     * @group Workspace
     *
     * @param  \App\Models\Workspace  $workspace
     * @return \App\Api\V1\Resources\WorkspaceResource
     */
    public function show(Workspace $workspace)
    {
        return new WorkspaceResource($workspace);
    }

    /**
     * Update the specified workspace.
     *
     * @group Workspace
     *
     * @param  \App\Api\V1\Requests\Workspace\UpdateRequest  $request
     * @param  \App\Models\Workspace  $workspace
     * @param  \App\Services\WorkspaceService  $workspaceService
     * @return \App\Api\V1\Resources\WorkspaceResource
     */
    public function update(UpdateRequest $request, Workspace $workspace, WorkspaceService $workspaceService)
    {
        return new WorkspaceResource($workspaceService->update($workspace, $request->validated()));
    }

    /**
     * Remove the specified workspace.
     *
     * @group Workspace
     *
     * @param  \App\Models\Workspace  $workspace
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Workspace $workspace)
    {
        $workspace->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
