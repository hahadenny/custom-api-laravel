<?php

namespace App\Api\V1\Controllers\Schedule;

use App\Api\V1\Requests\Schedule\Listing\LayerPlaylistPivotStoreRequest;
use App\Api\V1\Resources\PlaylistResource;
use App\Http\Controllers\Controller;
use App\Models\ChannelLayer;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use App\Services\Schedule\ScheduleLayerListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Methods pertaining to the children of a given channel layer in a schedule listing
 *
 * @group Schedule Layer
 */
class ScheduleLayerPlaylistController extends Controller
{
    public function __construct()
    {
        // $this->middleware(['can:view,channelLayer']);

        //# authorizeResource(resource model name to authorize, route param name for the model instance)
        //# controller methods will be mapped to their corresponding policy method
        //# e.g., show --> view, edit --> update
        //# https://laravel.com/docs/8.x/authorization#authorizing-resource-controllers
        // $this->authorizeResource(Schedule::class, 'schedule');
    }

    /**
     * Add the specified playlist to the layer in the scheduler.
     *
     * @return JsonResponse
     */
    public function store(LayerPlaylistPivotStoreRequest $request, ChannelLayer $layer, ScheduleLayerListingService $scheduleLayerListingService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $scheduleable_type = Str::contains(Str::lower($request->input('type')), 'playlistgroup') ? PlaylistGroup::class : Playlist::class;

        // @todo: change resource
        return (new PlaylistResource($scheduleLayerListingService->store($authUser, $layer, $request->input('scheduleable_id'), $scheduleable_type)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
