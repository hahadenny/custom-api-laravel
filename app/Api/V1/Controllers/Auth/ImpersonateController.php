<?php

namespace App\Api\V1\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\ImpersonateService;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    protected ImpersonateService $service;

    public function __construct(ImpersonateService $service)
    {
        $this->service = $service;
    }

    /**
     * Impersonate: take by admin company
     *
     * @group Authentication
     * @param Company $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Company $company)
    {
        $user = $company->users()->isAdmin()->firstOrFail();
        $token = $this->service->take(Auth::user(), $user);

        return $this->authResponse($token);
    }

    /**
     * Impersonate: Leave
     *
     * @group Authentication
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy()
    {
        return $this->authResponse($this->service->leave());
    }
}
