<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Company\IndexRequest;
use App\Api\V1\Requests\Company\StoreRequest;
use App\Api\V1\Requests\Company\UpdateRequest;
use App\Api\V1\Resources\CompanyResource;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyService;
use Symfony\Component\HttpFoundation\Response;

class CompanyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Company::class, 'company');
    }

    /**
     * Display a listing of companies.
     *
     * @group Company
     * @unauthenticated
     *
     * @param  \App\Api\V1\Requests\Company\IndexRequest  $request
     * @param  \App\Services\CompanyService  $companyService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request, CompanyService $companyService)
    {
        return CompanyResource::collection($companyService->listing($request->validated()));
    }

    /**
     * Store a newly created company.
     *
     * @group Company
     *
     * @param  \App\Api\V1\Requests\Company\StoreRequest  $request
     * @param  \App\Services\CompanyService  $companyService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, CompanyService $companyService)
    {
        return (new CompanyResource($companyService->storeWithAdmin($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified company.
     *
     * @group Company
     *
     * @param  \App\Models\Company  $company
     * @return \App\Api\V1\Resources\CompanyResource
     */
    public function show(Company $company)
    {
        return new CompanyResource($company);
    }

    /**
     * Update the specified company.
     *
     * @group Company
     *
     * @param  \App\Api\V1\Requests\Company\UpdateRequest  $request
     * @param  \App\Models\Company  $company
     * @return \App\Api\V1\Resources\CompanyResource
     */
    public function update(UpdateRequest $request, Company $company)
    {
        return new CompanyResource(tap($company)->update($request->validated()));
    }

    /**
     * Remove the specified company.
     *
     * @group Company
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Company $company)
    {
        $company->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function servers(CompanyService $companyService)
    {
        return response()->json([
            'server' => $companyService->getServers()
        ]);
    }
}
