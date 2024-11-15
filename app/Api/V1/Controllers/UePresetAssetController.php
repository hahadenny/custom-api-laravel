<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\UePresetAsset\BatchDestroyRequest;
use App\Api\V1\Requests\UePresetAsset\StoreRequest;
use App\Api\V1\Requests\UePresetAsset\UpdateRequest;
use App\Api\V1\Resources\UePresetAssetResource;
use App\Http\Controllers\Controller;
use App\Models\UePresetAsset;
use App\Services\UePresetAssetService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UePresetAssetController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->authorizeResource(UePresetAsset::class, 'ue_preset_asset');
    }

    /**
     * Display a listing of UE preset assets.
     *
     * @group UePresetAsset
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return UePresetAssetResource::collection($authUser->company->uePresetAssets()->orderByDesc('id')->paginate());
    }

    /**
     * Store a newly created UE preset asset.
     *
     * @group UePresetAsset
     *
     * @param  \App\Api\V1\Requests\UePresetAsset\StoreRequest  $request
     * @param  \App\Services\UePresetAssetService  $uePresetAssetService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, UePresetAssetService $uePresetAssetService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new UePresetAssetResource($uePresetAssetService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified UE preset asset.
     *
     * @group UePresetAsset
     *
     * @param  \App\Models\UePresetAsset  $uePresetAsset
     * @return \App\Api\V1\Resources\UePresetAssetResource
     */
    public function show(UePresetAsset $uePresetAsset)
    {
        return new UePresetAssetResource($uePresetAsset);
    }

    /**
     * Update the specified UE preset asset.
     *
     * @group UePresetAsset
     *
     * @param  \App\Api\V1\Requests\UePresetAsset\UpdateRequest  $request
     * @param  \App\Models\UePresetAsset  $uePresetAsset
     * @return \App\Api\V1\Resources\UePresetAssetResource
     */
    public function update(UpdateRequest $request, UePresetAsset $uePresetAsset)
    {
        return new UePresetAssetResource(tap($uePresetAsset)->update($request->validated()));
    }

    /**
     * Remove the specified UE preset asset.
     *
     * @group UePresetAsset
     *
     * @param  \App\Models\UePresetAsset  $uePresetAsset
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(UePresetAsset $uePresetAsset)
    {
        $uePresetAsset->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified UE preset assets.
     *
     * @group UePresetAsset
     *
     * @param  \App\Api\V1\Requests\UePresetAsset\BatchDestroyRequest  $request
     * @param  \App\Services\UePresetAssetService  $uePresetAssetService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, UePresetAssetService $uePresetAssetService)
    {
        $uePresetAssetService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
