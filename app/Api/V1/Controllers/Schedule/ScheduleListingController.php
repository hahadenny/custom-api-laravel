<?php

namespace App\Api\V1\Controllers\Schedule;

use App\Api\V1\Requests\Schedule\Listing\BatchDestroyRequest;
use App\Api\V1\Requests\Schedule\Listing\BatchUpdateRequest;
use App\Api\V1\Requests\Schedule\Listing\UpdateRequest;
use App\Api\V1\Resources\Schedule\ScheduleListingResource;
use App\Http\Controllers\Controller;
use App\Models\Schedule\ScheduleListing;
use App\Services\Schedule\ScheduleListingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Methods pertaining to the trees of entities in a Scheduler listing
 *
 * @group Schedule Listing
 */
class ScheduleListingController extends Controller
{
    public function __construct(protected ScheduleListingService $service)
    {
        // $this->middleware(['can:view,scheduleListing']);

        //# authorizeResource(resource model name to authorize, route param name for the model instance)
        //# controller methods will be mapped to their corresponding policy method
        //# e.g., show --> view, edit --> update
        //# https://laravel.com/docs/8.x/authorization#authorizing-resource-controllers
        // $this->authorizeResource(ScheduleListing::class, 'scheduleListing');
    }

    /**
     * Update the specified Schedule Listing entry
     *
     * @throws \Exception
     */
    public function update(UpdateRequest $request, ScheduleListing $listingNode, ScheduleListingService $service)
    {
        return new ScheduleListingResource($service->update($listingNode, $request->validated()));
    }

    /**
     * Update the specified Schedule Listing entries
     */
    public function batchUpdate(BatchUpdateRequest $request, ScheduleListingService $service)
    {
        return ScheduleListingResource::collection($service->batchUpdate($request->validated()));
    }

    /**
     * Remove the specified items from the scheduler
     */
    public function batchDestroy(BatchDestroyRequest $request, ScheduleListingService $service) : JsonResponse
    {
        $service->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

}
