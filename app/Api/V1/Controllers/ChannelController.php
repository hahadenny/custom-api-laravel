<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Channel\BatchDestroyRequest;
use App\Api\V1\Requests\Channel\BatchDuplicateRequest;
use App\Api\V1\Requests\Channel\BatchUpdateRequest;
use App\Api\V1\Requests\Channel\CompanySyncRequest;
use App\Api\V1\Requests\Channel\StoreRequest;
use App\Api\V1\Requests\Channel\SyncRequest;
use App\Api\V1\Requests\Channel\UpdateRequest;
use App\Api\V1\Resources\ChannelResource;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Company;
use App\Services\ChannelService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ChannelController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->authorizeResource(Channel::class, 'channel');
    }

    /**
     * Display a listing of channels.
     *
     * @group Channel
     *
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(ChannelService $channelService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return ChannelResource::collection($channelService->listing($authUser));
    }

    /**
     * Store a newly created channel.
     *
     * @group Channel
     *
     * @param  \App\Api\V1\Requests\Channel\StoreRequest  $request
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, ChannelService $channelService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new ChannelResource($channelService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified channel.
     *
     * @group Channel
     *
     * @param  \App\Models\Channel  $channel
     * @return \App\Api\V1\Resources\ChannelResource
     */
    public function show(Channel $channel)
    {
        return new ChannelResource($channel->loadMissing(['parent:id,name,type,is_preview']));
    }

    /**
     * Update the specified channel.
     *
     * @group Channel
     *
     * @param  \App\Api\V1\Requests\Channel\UpdateRequest  $request
     * @param  \App\Models\Channel  $channel
     * @param  \App\Services\ChannelService  $channelService
     * @return \App\Api\V1\Resources\ChannelResource
     */
    public function update(UpdateRequest $request, Channel $channel, ChannelService $channelService)
    {
        return new ChannelResource($channelService->update($channel, $request->validated()));
    }

    /**
     * Update the specified channels.
     *
     * @group Channel
     *
     * @param  \App\Api\V1\Requests\Channel\BatchUpdateRequest  $request
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, ChannelService $channelService)
    {
        return ChannelResource::collection($channelService->batchUpdate($request->validated()));
    }

    /**
     * Duplicate the specified channels.
     *
     * @group Channel
     *
     * @param  \App\Api\V1\Requests\Channel\BatchDuplicateRequest  $request
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDuplicate(BatchDuplicateRequest $request, ChannelService $channelService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $channelService->batchDuplicate($authUser, $request->validated());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified channel.
     *
     * @group Channel
     *
     * @param  \App\Models\Channel  $channel
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Channel $channel, ChannelService $channelService)
    {
        $channelService->delete($channel);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified channels.
     *
     * @group Channel
     *
     * @param  \App\Api\V1\Requests\Channel\BatchDestroyRequest  $request
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, ChannelService $channelService)
    {
        $channelService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Sync the channels.
     *
     * @group Channel
     *
     * @param  \App\Api\V1\Requests\Channel\SyncRequest  $request
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\Response
     */
    public function sync(SyncRequest $request, ChannelService $channelService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $channelService->sync(null, $authUser, $request->validated());
        return response()->noContent();
    }

    /**
     * Sync the company channels.
     *
     * @group Channel
     * @unauthenticated
     *
     * @param  \App\Api\V1\Requests\Channel\CompanySyncRequest  $request
     * @param  \App\Models\Company  $company
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\Response
     */
    public function companySync(CompanySyncRequest $request, Company $company, ChannelService $channelService)
    {
        $channelService->sync($company, null, $request->validated());
        return response()->noContent();
    }
}
