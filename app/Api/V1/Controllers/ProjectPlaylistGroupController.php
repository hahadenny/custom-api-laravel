<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\PlaylistGroup\BatchDestroyRequest;
use App\Api\V1\Requests\PlaylistGroup\BatchDuplicateRequest;
use App\Api\V1\Requests\PlaylistGroup\BatchUngroupRequest;
use App\Api\V1\Requests\PlaylistGroup\BatchUpdateRequest;
use App\Api\V1\Requests\PlaylistGroup\StoreRequest;
use App\Api\V1\Requests\PlaylistGroup\UpdateRequest;
use App\Api\V1\Resources\PlaylistGroupResource;
use App\Http\Controllers\Controller;
use App\Models\PlaylistGroup;
use App\Models\Project;
use App\Services\PlaylistGroupService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProjectPlaylistGroupController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->middleware(['can:view,project']);
        $this->authorizeResource(PlaylistGroup::class, 'playlist_group');
    }

    /**
     * Display a listing of playlist groups.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Project $project)
    {
        return PlaylistGroupResource::collection(
            $project->playlistGroups()->with(['parentListingPivot'])->orderByDesc('id')->paginate()
        );
    }

    /**
     * Store a newly created playlist group.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Api\V1\Requests\PlaylistGroup\StoreRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistGroupService  $playlistGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, Project $project, PlaylistGroupService $playlistGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new PlaylistGroupResource($playlistGroupService->store($authUser, $project, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified playlist group.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Models\Project  $project
     * @param  \App\Models\PlaylistGroup  $playlistGroup
     * @return \App\Api\V1\Resources\PlaylistGroupResource
     */
    public function show(Project $project, PlaylistGroup $playlistGroup)
    {
        $project->playlistGroups()->findOrFail($playlistGroup->id);
        return new PlaylistGroupResource($playlistGroup);
    }

    /**
     * Update the specified playlist group.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Api\V1\Requests\PlaylistGroup\UpdateRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Models\PlaylistGroup  $playlistGroup
     * @param  \App\Services\PlaylistGroupService  $playlistGroupService
     * @return \App\Api\V1\Resources\PlaylistGroupResource
     */
    public function update(UpdateRequest $request, Project $project, PlaylistGroup $playlistGroup, PlaylistGroupService $playlistGroupService)
    {
        $project->playlistGroups()->findOrFail($playlistGroup->id);
        return new PlaylistGroupResource($playlistGroupService->update($playlistGroup, $request->validated()));
    }

    /**
     * Update the specified playlist groups.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Api\V1\Requests\PlaylistGroup\BatchUpdateRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistGroupService  $playlistGroupService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, Project $project, PlaylistGroupService $playlistGroupService)
    {
        return PlaylistGroupResource::collection($playlistGroupService->batchUpdate($request->validated()));
    }

    /**
     * Duplicate the specified playlist groups.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Api\V1\Requests\PlaylistGroup\BatchDuplicateRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistGroupService  $playlistGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDuplicate(BatchDuplicateRequest $request, Project $project, PlaylistGroupService $playlistGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $playlistGroupService->batchDuplicate($authUser, $request->validated());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Ungroup the specified playlist groups.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Api\V1\Requests\PlaylistGroup\BatchUngroupRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistGroupService  $playlistGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchUngroup(BatchUngroupRequest $request, Project $project, PlaylistGroupService $playlistGroupService)
    {
        $playlistGroupService->batchUngroup($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified playlist group.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Models\Project  $project
     * @param  \App\Models\PlaylistGroup  $playlistGroup
     * @param  \App\Services\PlaylistGroupService  $playlistGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Project $project, PlaylistGroup $playlistGroup, PlaylistGroupService $playlistGroupService)
    {
        $project->playlistGroups()->findOrFail($playlistGroup->id);
        $playlistGroupService->delete($playlistGroup);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified playlist groups.
     *
     * @group Project Playlist Group
     *
     * @param  \App\Api\V1\Requests\PlaylistGroup\BatchDestroyRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistGroupService  $playlistGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, Project $project, PlaylistGroupService $playlistGroupService)
    {
        $playlistGroupService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
