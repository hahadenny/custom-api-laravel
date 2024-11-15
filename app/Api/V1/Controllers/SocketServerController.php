<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\SocketServer\StoreRequest;
use App\Api\V1\Resources\SocketServerResource;
use App\Http\Controllers\Controller;
use App\Models\SocketServer;
use App\Services\SocketServerService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SocketServerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(SocketServer::class, 'socket_server');
    }

    /**
     * Display a listing of socket servers.
     *
     * @group Socket Server
     * @unauthenticated
     *
     * @param  \App\Services\SocketServerService  $socketServerService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(SocketServerService $socketServerService)
    {
        return SocketServerResource::collection($socketServerService->listing());
    }

    /**
     * Store a newly created socket server.
     *
     * @group Socket Server
     *
     * @param  \App\Api\V1\Requests\SocketServer\StoreRequest  $request
     * @param  \App\Services\CompanyService  $companyService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, SocketServerService $service)
    {
        return (new SocketServerResource($service->store($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove the specified socket server.
     *
     * @group Socket Server
     *
     * @param  \App\Models\SocketServer  $socketServer
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(SocketServer $socketServer)
    {
        $socketServer->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function servers(SocketServerService $service)
    {
        return response()->json(['server' => $service->getServers()]);
    }
}
