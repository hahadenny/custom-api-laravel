<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\UePreset\BatchDestroyRequest;
use App\Api\V1\Requests\UePreset\BatchUpdateRequest;
use App\Api\V1\Requests\UePreset\StoreRequest;
use App\Api\V1\Requests\UePreset\UpdateRequest;
use App\Api\V1\Resources\UePresetResource;
use App\Http\Controllers\Controller;
use App\Models\UePreset;
use App\Services\UePresetService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UePresetController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->authorizeResource(UePreset::class, 'ue_preset');
    }

    /**
     * Display a listing of UE presets.
     *
     * @group UePreset
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return UePresetResource::collection($authUser->company->uePresets()->orderByDesc('id')->paginate());
    }

    /**
     * Store a newly created UE preset.
     *
     * @group UePreset
     *
     * @param  \App\Api\V1\Requests\UePreset\StoreRequest  $request
     * @param  \App\Services\UePresetService  $uePresetService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, UePresetService $uePresetService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new UePresetResource($uePresetService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified UE preset.
     *
     * @group UePreset
     *
     * @param  \App\Models\UePreset  $uePreset
     * @return \App\Api\V1\Resources\UePresetResource
     */
    public function show(UePreset $uePreset)
    {
        return new UePresetResource($uePreset);
    }

    /**
     * Update the specified UE preset.
     *
     * @group UePreset
     *
     * @param  \App\Api\V1\Requests\UePreset\UpdateRequest  $request
     * @param  \App\Models\UePreset  $uePreset
     * @return \App\Api\V1\Resources\UePresetResource
     */
    public function update(UpdateRequest $request, UePreset $uePreset)
    {
        return new UePresetResource(tap($uePreset)->update($request->validated()));
    }

    /**
     * Update the specified UE presets.
     *
     * @group UePreset
     *
     * @param  \App\Api\V1\Requests\UePreset\BatchUpdateRequest  $request
     * @param  \App\Services\UePresetService  $uePresetService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, UePresetService $uePresetService)
    {
        return UePresetResource::collection($uePresetService->batchUpdate($request->validated()));
    }

    /**
     * Remove the specified UE preset.
     *
     * @group UePreset
     *
     * @param  \App\Models\UePreset  $uePreset
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(UePreset $uePreset)
    {
        $uePreset->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified UE presets.
     *
     * @group UePreset
     *
     * @param  \App\Api\V1\Requests\UePreset\BatchDestroyRequest  $request
     * @param  \App\Services\UePresetService  $uePresetService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, UePresetService $uePresetService)
    {
        $uePresetService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
