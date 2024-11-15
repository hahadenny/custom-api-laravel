<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\UePresetGroup\BatchDestroyRequest;
use App\Api\V1\Requests\UePresetGroup\BatchUpdateRequest;
use App\Api\V1\Requests\UePresetGroup\StoreRequest;
use App\Api\V1\Requests\UePresetGroup\UpdateRequest;
use App\Api\V1\Resources\UePresetGroupResource;
use App\Http\Controllers\Controller;
use App\Models\UePresetGroup;
use App\Services\UePresetGroupService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UePresetGroupController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->authorizeResource(UePresetGroup::class, 'ue_preset_group');
    }

    /**
     * Display a listing of UE preset groups.
     *
     * @group UePresetGroup
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return UePresetGroupResource::collection($authUser->company->uePresetGroups()->orderByDesc('id')->paginate());
    }

    /**
     * Store a newly created UE preset group.
     *
     * @group UePresetGroup
     *
     * @param  \App\Api\V1\Requests\UePresetGroup\StoreRequest  $request
     * @param  \App\Services\UePresetGroupService  $uePresetGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, UePresetGroupService $uePresetGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new UePresetGroupResource($uePresetGroupService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified UE preset group.
     *
     * @group UePresetGroup
     *
     * @param  \App\Models\UePresetGroup  $uePresetGroup
     * @return \App\Api\V1\Resources\UePresetGroupResource
     */
    public function show(UePresetGroup $uePresetGroup)
    {
        return new UePresetGroupResource($uePresetGroup);
    }

    /**
     * Update the specified UE preset group.
     *
     * @group UePresetGroup
     *
     * @param  \App\Api\V1\Requests\UePresetGroup\UpdateRequest  $request
     * @param  \App\Models\UePresetGroup  $uePresetGroup
     * @param  \App\Services\UePresetGroupService  $uePresetGroupService
     * @return \App\Api\V1\Resources\UePresetGroupResource
     */
    public function update(UpdateRequest $request, UePresetGroup $uePresetGroup, UePresetGroupService $uePresetGroupService)
    {
        return new UePresetGroupResource($uePresetGroupService->update($uePresetGroup, $request->validated()));
    }

    /**
     * Update the specified UE preset groups.
     *
     * @group UePresetGroup
     *
     * @param  \App\Api\V1\Requests\UePresetGroup\BatchUpdateRequest  $request
     * @param  \App\Services\UePresetGroupService  $uePresetGroupService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, UePresetGroupService $uePresetGroupService)
    {
        return UePresetGroupResource::collection($uePresetGroupService->batchUpdate($request->validated()));
    }

    /**
     * Remove the specified UE preset group.
     *
     * @group UePresetGroup
     *
     * @param  \App\Models\UePresetGroup  $uePresetGroup
     * @param  \App\Services\UePresetGroupService  $uePresetGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(UePresetGroup $uePresetGroup, UePresetGroupService $uePresetGroupService)
    {
        $uePresetGroupService->delete($uePresetGroup);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified UE preset groups.
     *
     * @group UePresetGroup
     *
     * @param  \App\Api\V1\Requests\UePresetGroup\BatchDestroyRequest  $request
     * @param  \App\Services\UePresetGroupService  $uePresetGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, UePresetGroupService $uePresetGroupService)
    {
        $uePresetGroupService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
