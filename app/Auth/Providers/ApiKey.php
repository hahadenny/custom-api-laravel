<?php

namespace App\Auth\Providers;

use App\Models\Company;
use App\Models\User;
use Dingo\Api\Contract\Auth\Provider;
use Dingo\Api\Routing\Route;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ApiKey implements Provider
{
    public function authenticate(Request $request, Route $route)
    {
        $apiKey = $request->input('api_key');

        if (empty($apiKey) || ! is_string($apiKey)) {
            throw new UnauthorizedHttpException('The API key is invalid.');
        }

        /** @var \App\Models\Company $company */
        $company = Company::query()
            ->where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (is_null($company)) {
            throw new UnauthorizedHttpException('The API key is invalid.');
        }

        $user = User::query()->withinCompany($company)->isAdmin()->orderBy('id')->first();

        if (is_null($user)) {
            throw new UnauthorizedHttpException('The API key is invalid.');
        }

        return $user;
    }
}
