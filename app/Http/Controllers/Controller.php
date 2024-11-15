<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function authResponse($token, $status = Response::HTTP_OK) : JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'token' => $token,
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60
        ], $status);
    }
}
