<?php

namespace App\Auth\Providers;

use App\Models\User;
use Dingo\Api\Contract\Auth\Provider;
use Dingo\Api\Routing\Route;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class HealthCheckSecret implements Provider
{
    public function authenticate(Request $request, Route $route)
    {
        $secret = $request->input('oh-dear-health-check-secret');

        if (empty($secret) || ! is_string($secret) || $secret !== config('oh_dear_endpoint.secret')) {
            throw new UnauthorizedHttpException('The health check secret key is invalid.');
        }

        // can be any user; we won't be interacting with Porta entities
        return User::query()->isSuperAdmin()->first();
    }
}
