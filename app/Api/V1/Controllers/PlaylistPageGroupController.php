<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\PageGroup\BatchDestroyRequest;
use App\Api\V1\Requests\PageGroup\BatchDuplicateRequest;
use App\Api\V1\Requests\PageGroup\BatchRestoreRequest;
use App\Api\V1\Requests\PageGroup\BatchUngroupRequest;
use App\Api\V1\Requests\PageGroup\BatchUpdateRequest;
use App\Api\V1\Requests\PageGroup\PlaySequenceRequest;
use App\Api\V1\Requests\PageGroup\StoreRequest;
use App\Api\V1\Requests\PageGroup\UpdateRequest;
use App\Api\V1\Resources\PageGroupResource;
use App\Http\Controllers\Controller;
use App\Models\PageGroup;
use App\Models\Playlist;
use App\Services\PageGroupService;
use App\Services\Schedule\PageGroupSequenceService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PlaylistPageGroupController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->middleware(['can:view,playlist']);
        $this->authorizeResource(PageGroup::class, 'page_group');
    }

    /**
     * Display a listing of page groups.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Models\Playlist  $playlist
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Playlist $playlist)
    {
        return PageGroupResource::collection(
            $playlist->pageGroups()->with(['parentListingPivot'])->orderByDesc('id')->paginate()
        );
    }

    /**
     * Store a newly created page group.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Api\V1\Requests\PageGroup\StoreRequest  $request
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PageGroupService  $pageGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, Playlist $playlist, PageGroupService $pageGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new PageGroupResource($pageGroupService->store($authUser, $playlist, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified page group.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Models\PageGroup  $pageGroup
     * @return \App\Api\V1\Resources\PageGroupResource
     */
    public function show(Playlist $playlist, PageGroup $pageGroup)
    {
        $playlist->pageGroups()->findOrFail($pageGroup->id);
        return new PageGroupResource($pageGroup);
    }

    /**
     * Update the specified page group.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Api\V1\Requests\PageGroup\UpdateRequest  $request
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Models\PageGroup  $pageGroup
     * @param  \App\Services\PageGroupService  $pageGroupService
     * @return \App\Api\V1\Resources\PageGroupResource
     */
    public function update(UpdateRequest $request, Playlist $playlist, PageGroup $pageGroup, PageGroupService $pageGroupService)
    {
        $playlist->pageGroups()->findOrFail($pageGroup->id);
        return new PageGroupResource($pageGroupService->update($pageGroup, $playlist, $request->validated()));
    }

    /**
     * Update the specified page groups.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Api\V1\Requests\PageGroup\BatchUpdateRequest  $request
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PageGroupService  $pageGroupService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, Playlist $playlist, PageGroupService $pageGroupService)
    {
        return PageGroupResource::collection($pageGroupService->batchUpdate($playlist, $request->validated()));
    }

    /**
     * Duplicate the specified page groups.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Api\V1\Requests\PageGroup\BatchDuplicateRequest  $request
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PageGroupService  $pageGroupService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchDuplicate(BatchDuplicateRequest $request, Playlist $playlist, PageGroupService $pageGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return JsonResource::collection($pageGroupService->batchDuplicate($authUser, $playlist, $request->validated()));
    }

    /**
     * Ungroup the specified page groups.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Api\V1\Requests\PageGroup\BatchUngroupRequest  $request
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PageGroupService  $pageGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchUngroup(BatchUngroupRequest $request, Playlist $playlist, PageGroupService $pageGroupService)
    {
        $pageGroupService->batchUngroup($playlist, $request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified page group.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Models\PageGroup  $pageGroup
     * @param  \App\Services\PageGroupService  $pageGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Playlist $playlist, PageGroup $pageGroup, PageGroupService $pageGroupService)
    {
        $playlist->pageGroups()->findOrFail($pageGroup->id);
        $pageGroupService->delete($pageGroup, $playlist);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified page groups.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Api\V1\Requests\PageGroup\BatchDestroyRequest  $request
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PageGroupService  $pageGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, Playlist $playlist, PageGroupService $pageGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $pageGroupService->batchDelete($authUser, $playlist, $request->validated());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Restore the specified page groups.
     *
     * @group Playlist Page Group
     *
     * @param  \App\Api\V1\Requests\PageGroup\BatchRestoreRequest  $request
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PageGroupService  $pageGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchRestore(BatchRestoreRequest $request, Playlist $playlist, PageGroupService $pageGroupService)
    {
        $pageGroupService->batchRestore($playlist, $request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Play Page Group Pages in sequential order
     *
     * @group Playlist Page Group
     *
     * @param PlaySequenceRequest      $request
     * @param Playlist                 $playlist
     * @param PageGroup                $pageGroup
     * @param PageGroupSequenceService $sequenceService
     *
     * @return JsonResponse
     */
    public function playSequence(PlaySequenceRequest $request, Playlist $playlist, PageGroup $pageGroup, PageGroupSequenceService $sequenceService)
    {
        $sequenceService->play($playlist, $pageGroup);

        return response()->json(null,Response::HTTP_CREATED);
    }
}
