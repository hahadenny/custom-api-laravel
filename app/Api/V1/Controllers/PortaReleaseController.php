<?php

namespace App\Api\V1\Controllers;

use App\Services\ReleaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortaReleaseController extends \App\Http\Controllers\Controller
{
    public function __construct(protected ReleaseService $service) {}

    public function index(Request $request) : JsonResource
    {
        return new JsonResource($this->service->listing($request->all()));
    }
}
