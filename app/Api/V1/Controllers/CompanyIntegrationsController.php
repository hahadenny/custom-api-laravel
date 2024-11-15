<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\CompanyIntegrations;
use App\Api\V1\Requests\CompanyIntegrations\UpdateRequest;
use App\Services\CompanyIntegrationsService;
use App\Api\V1\Resources\CompanyIntegrationsResource;

class CompanyIntegrationsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return $authUser->company?->companyIntegrations()->get()->toArray();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(UpdateRequest $request, CompanyIntegrationsService $companyIntegrationsService)
    {
        $authUser = Auth::guard()->user();

        return (new CompanyIntegrationsResource($companyIntegrationsService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
