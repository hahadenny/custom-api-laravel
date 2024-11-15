<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\ChannelGroup\BatchDestroyRequest;
use App\Api\V1\Requests\ChannelGroup\BatchDuplicateRequest;
use App\Api\V1\Requests\ChannelGroup\BatchUngroupRequest;
use App\Api\V1\Requests\ChannelGroup\BatchUpdateRequest;
use App\Api\V1\Requests\ChannelGroup\StoreRequest;
use App\Api\V1\Requests\ChannelGroup\UpdateRequest;
use App\Api\V1\Resources\ChannelGroupResource;
use App\Http\Controllers\Controller;
use App\Models\ChannelGroup;
use App\Services\ChannelGroupService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ChannelGroupController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->authorizeResource(ChannelGroup::class, 'channel_group');
    }

    /**
     * Display a listing of channel groups.
     *
     * @group Channel Group
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return ChannelGroupResource::collection(
            $authUser->company->channelGroups()->with(['parentListingPivot'])->orderByDesc('id')->paginate()
        );
    }

    /**
     * Store a newly created channel group.
     *
     * @group Channel Group
     *
     * @param  \App\Api\V1\Requests\ChannelGroup\StoreRequest  $request
     * @param  \App\Services\ChannelGroupService  $channelGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, ChannelGroupService $channelGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new ChannelGroupResource($channelGroupService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified channel group.
     *
     * @group Channel Group
     *
     * @param  \App\Models\ChannelGroup  $channelGroup
     * @return \App\Api\V1\Resources\ChannelGroupResource
     */
    public function show(ChannelGroup $channelGroup)
    {
        return new ChannelGroupResource($channelGroup);
    }

    /**
     * Update the specified channel group.
     *
     * @group Channel Group
     *
     * @param  \App\Api\V1\Requests\ChannelGroup\UpdateRequest  $request
     * @param  \App\Models\ChannelGroup  $channelGroup
     * @param  \App\Services\ChannelGroupService  $channelGroupService
     * @return \App\Api\V1\Resources\ChannelGroupResource
     */
    public function update(UpdateRequest $request, ChannelGroup $channelGroup, ChannelGroupService $channelGroupService)
    {
        return new ChannelGroupResource($channelGroupService->update($channelGroup, $request->validated()));
    }

    /**
     * Update the specified channel groups.
     *
     * @group Channel Group
     *
     * @param  \App\Api\V1\Requests\ChannelGroup\BatchUpdateRequest  $request
     * @param  \App\Services\ChannelGroupService  $channelGroupService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, ChannelGroupService $channelGroupService)
    {
        return ChannelGroupResource::collection($channelGroupService->batchUpdate($request->validated()));
    }

    /**
     * Duplicate the specified channel groups.
     *
     * @group Channel Group
     *
     * @param  \App\Api\V1\Requests\ChannelGroup\BatchDuplicateRequest  $request
     * @param  \App\Services\ChannelGroupService  $channelGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDuplicate(BatchDuplicateRequest $request, ChannelGroupService $channelGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $channelGroupService->batchDuplicate($authUser, $request->validated());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Ungroup the specified channel groups.
     *
     * @group Channel Group
     *
     * @param  \App\Api\V1\Requests\ChannelGroup\BatchUngroupRequest  $request
     * @param  \App\Services\ChannelGroupService  $channelGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchUngroup(BatchUngroupRequest $request, ChannelGroupService $channelGroupService)
    {
        $channelGroupService->batchUngroup($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified channel group.
     *
     * @group Channel Group
     *
     * @param  \App\Models\ChannelGroup  $channelGroup
     * @param  \App\Services\ChannelGroupService  $channelGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(ChannelGroup $channelGroup, ChannelGroupService $channelGroupService)
    {
        $channelGroupService->delete($channelGroup);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified channel groups.
     *
     * @group Channel Group
     *
     * @param  \App\Api\V1\Requests\ChannelGroup\BatchDestroyRequest  $request
     * @param  \App\Services\ChannelGroupService  $channelGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, ChannelGroupService $channelGroupService)
    {
        $channelGroupService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
