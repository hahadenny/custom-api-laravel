<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;

/**
 * This authenticates a user if possible or do nothing.
 * The main authentication is done by Dingo middlewares.
 * This middleware sets a current authentication guard to be a default one for following functions to work:
 * Auth::guard()->user(), auth()->user(), request()->user(), etc.
 * This middleware is used when a current authentication guard is not a default one.
 */
class AuthenticateSilently extends Authenticate
{
    protected function unauthenticated($request, array $guards)
    {
        // Be silent.
    }
}
