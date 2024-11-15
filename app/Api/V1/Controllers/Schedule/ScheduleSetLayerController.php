<?php

namespace App\Api\V1\Controllers\Schedule;

use App\Api\V1\Requests\Schedule\ScheduleLayer\UpsertRequest;
use App\Api\V1\Resources\Schedule\ChannelLayerResource;
use App\Models\Schedule\ScheduleSet;
use App\Services\Schedule\ChannelLayerService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Methods pertaining to the layers of a given channel
 *
 * @group Schedule Layer
 */
class ScheduleSetLayerController extends \App\Http\Controllers\Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        // $this->middleware(['can:view,channel']);

        //# authorizeResource(resource model name to authorize, route param name for the model instance)
        //# controller methods will be mapped to their corresponding policy method
        //# e.g., show --> view, edit --> update
        //# https://laravel.com/docs/8.x/authorization#authorizing-resource-controllers
        // $this->authorizeResource(ChannelLayer::class, 'channelLayer');
    }

    /**
     * Store a newly created layer for a given schedule set
     *
     * @param \App\Api\V1\Requests\Schedule\ScheduleLayer\UpsertRequest $request
     * @param \App\Models\Schedule\ScheduleSet                          $scheduleSet
     * @param \App\Services\Schedule\ChannelLayerService                $channelLayerService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UpsertRequest $request, ScheduleSet $scheduleSet, ChannelLayerService $channelLayerService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new ChannelLayerResource($channelLayerService->store($authUser, $scheduleSet, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
