<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\TemplateGroup\BatchDestroyRequest;
use App\Api\V1\Requests\TemplateGroup\BatchDuplicateRequest;
use App\Api\V1\Requests\TemplateGroup\BatchUngroupRequest;
use App\Api\V1\Requests\TemplateGroup\BatchUpdateRequest;
use App\Api\V1\Requests\TemplateGroup\StoreRequest;
use App\Api\V1\Requests\TemplateGroup\UpdateRequest;
use App\Api\V1\Resources\TemplateGroupResource;
use App\Http\Controllers\Controller;
use App\Models\TemplateGroup;
use App\Services\TemplateGroupService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TemplateGroupController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->authorizeResource(TemplateGroup::class, 'template_group');
    }

    /**
     * Display a listing of template groups.
     *
     * @group TemplateGroup
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return TemplateGroupResource::collection(
            $authUser->company->templateGroups()->with(['parentListingPivot'])->orderByDesc('id')->paginate()
        );
    }

    /**
     * Store a newly created template group.
     *
     * @group TemplateGroup
     *
     * @param  \App\Api\V1\Requests\TemplateGroup\StoreRequest  $request
     * @param  \App\Services\TemplateGroupService  $templateGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, TemplateGroupService $templateGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new TemplateGroupResource($templateGroupService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified template group.
     *
     * @group TemplateGroup
     *
     * @param  \App\Models\TemplateGroup  $templateGroup
     * @return \App\Api\V1\Resources\TemplateGroupResource
     */
    public function show(TemplateGroup $templateGroup)
    {
        return new TemplateGroupResource($templateGroup);
    }

    /**
     * Update the specified template group.
     *
     * @group TemplateGroup
     *
     * @param  \App\Api\V1\Requests\TemplateGroup\UpdateRequest  $request
     * @param  \App\Models\TemplateGroup  $templateGroup
     * @param  \App\Services\TemplateGroupService  $templateGroupService
     * @return \App\Api\V1\Resources\TemplateGroupResource
     */
    public function update(UpdateRequest $request, TemplateGroup $templateGroup, TemplateGroupService $templateGroupService)
    {
        return new TemplateGroupResource($templateGroupService->update($templateGroup, $request->validated()));
    }

    /**
     * Update the specified template groups.
     *
     * @group TemplateGroup
     *
     * @param  \App\Api\V1\Requests\TemplateGroup\BatchUpdateRequest  $request
     * @param  \App\Services\TemplateGroupService  $templateGroupService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, TemplateGroupService $templateGroupService)
    {
        return TemplateGroupResource::collection($templateGroupService->batchUpdate($request->validated()));
    }

    /**
     * Duplicate the specified template groups.
     *
     * @group TemplateGroup
     *
     * @param  \App\Api\V1\Requests\TemplateGroup\BatchDuplicateRequest  $request
     * @param  \App\Services\TemplateGroupService  $templateGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDuplicate(BatchDuplicateRequest $request, TemplateGroupService $templateGroupService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $templateGroupService->batchDuplicate($authUser, $request->validated());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Ungroup the specified template groups.
     *
     * @group TemplateGroup
     *
     * @param  \App\Api\V1\Requests\TemplateGroup\BatchUngroupRequest  $request
     * @param  \App\Services\TemplateGroupService  $templateGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchUngroup(BatchUngroupRequest $request, TemplateGroupService $templateGroupService)
    {
        $templateGroupService->batchUngroup($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified template group.
     *
     * @group TemplateGroup
     *
     * @param  \App\Models\TemplateGroup  $templateGroup
     * @param  \App\Services\TemplateGroupService  $templateGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(TemplateGroup $templateGroup, TemplateGroupService $templateGroupService)
    {
        $templateGroupService->delete($templateGroup);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified template groups.
     *
     * @group TemplateGroup
     *
     * @param  \App\Api\V1\Requests\TemplateGroup\BatchDestroyRequest  $request
     * @param  \App\Services\TemplateGroupService  $templateGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, TemplateGroupService $templateGroupService)
    {
        $templateGroupService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Restore the specified template groups.
     *
     * @group TemplateGroup
     *
     * @param  \App\Api\V1\Requests\TemplateGroup\BatchDestroyRequest  $request
     * @param  \App\Services\TemplateGroupService  $templateGroupService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchRestore(BatchDestroyRequest $request, TemplateGroupService $templateGroupService)
    {
        ray($request->validated())->blue()->label('group batchRestore() -> $request->validated()');
        $templateGroupService->batchRestore($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
