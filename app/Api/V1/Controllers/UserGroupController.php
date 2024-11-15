<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\UserGroup\BatchDestroyRequest;
use App\Api\V1\Requests\UserGroup\BatchUngroupRequest;
use App\Api\V1\Requests\UserGroup\BatchUpdateRequest;
use App\Api\V1\Requests\UserGroup\StoreRequest;
use App\Api\V1\Requests\UserGroup\UpdateRequest;
use App\Api\V1\Resources\UserGroupResource;
use App\Http\Controllers\Controller;
use App\Models\UserGroup;
use App\Services\UserGroupService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserGroupController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->authorizeResource(UserGroup::class, 'user_group');
    }

    /**
     * Display a listing of user groups.
     *
     * @group UserGroup
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return UserGroupResource::collection($authUser->company->userGroups()->orderByDesc('id')->get());
    }

    /**
     * Store a newly created user group.
     *
     * @group UserGroup
     *
     * @param  \App\Api\V1\Requests\UserGroup\StoreRequest  $request
     * @param  \App\Services\UserGroupService  $userGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, UserGroupService $userGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new UserGroupResource($userGroupService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified user group.
     *
     * @group UserGroup
     *
     * @param  \App\Models\UserGroup  $userGroup
     * @return \App\Api\V1\Resources\UserGroupResource
     */
    public function show(UserGroup $userGroup)
    {
        return new UserGroupResource($userGroup);
    }

    /**
     * Update the specified user group.
     *
     * @group UserGroup
     *
     * @param  \App\Api\V1\Requests\UserGroup\UpdateRequest  $request
     * @param  \App\Models\UserGroup  $userGroup
     * @param  \App\Services\UserGroupService  $userGroupService
     * @return \App\Api\V1\Resources\UserGroupResource
     */
    public function update(UpdateRequest $request, UserGroup $userGroup, UserGroupService $userGroupService)
    {
        return new UserGroupResource($userGroupService->update($userGroup, $request->validated()));
    }

    /**
     * Update the specified user groups.
     *
     * @group UserGroup
     *
     * @param  \App\Api\V1\Requests\UserGroup\BatchUpdateRequest  $request
     * @param  \App\Services\UserGroupService  $userGroupService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, UserGroupService $userGroupService)
    {
        return UserGroupResource::collection($userGroupService->batchUpdate($request->validated()));
    }

    /**
     * Ungroup the specified user groups.
     *
     * @group UserGroup
     *
     * @param  \App\Api\V1\Requests\UserGroup\BatchUngroupRequest  $request
     * @param  \App\Services\UserGroupService  $userGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchUngroup(BatchUngroupRequest $request, UserGroupService $userGroupService)
    {
        $userGroupService->batchUngroup($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified user group.
     *
     * @group UserGroup
     *
     * @param  \App\Models\UserGroup  $userGroup
     * @param  \App\Services\UserGroupService  $userGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(UserGroup $userGroup, UserGroupService $userGroupService)
    {
        $userGroupService->delete($userGroup);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified user groups.
     *
     * @group UserGroup
     *
     * @param  \App\Api\V1\Requests\UserGroup\BatchDestroyRequest  $request
     * @param  \App\Services\UserGroupService  $userGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, UserGroupService $userGroupService)
    {
        $userGroupService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
