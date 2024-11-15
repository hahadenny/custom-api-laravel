<?php

namespace App\Api\V1\Controllers\Auth;

use App\Http\Controllers\Controller;
use Auth;

class RefreshController extends Controller
{
    /**
     * Refresh a token.
     *
     * @group Authentication
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $token = Auth::guard()->refresh();

        return $this->authResponse($token);
    }
}
