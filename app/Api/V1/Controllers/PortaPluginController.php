<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Plugin\StoreRequest;
use App\Services\PluginService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class PortaPluginController extends \App\Http\Controllers\Controller
{
    public function __construct(protected PluginService $service) {}

    public function index(Request $request)
    {
        return new JsonResource($this->service->listing($request->all()));
    }

    public function store(StoreRequest $request)
    {
        $this->service->store($request->validated());
        return response()->json(null, Response::HTTP_CREATED);
    }
}
