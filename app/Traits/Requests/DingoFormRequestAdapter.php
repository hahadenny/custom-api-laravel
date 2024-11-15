<?php

namespace App\Traits\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait DingoFormRequestAdapter
{
    protected function failedAuthorization()
    {
        throw new AccessDeniedHttpException((new AuthorizationException())->getMessage());
    }
}
