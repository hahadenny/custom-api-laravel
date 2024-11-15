<?php

namespace App\Api\V1\Controllers\Auth;

use App\Api\V1\Requests\SignUpRequest;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Auth;
use Illuminate\Http\Response;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SignUpController extends Controller
{
    /**
     * Sign up the user.
     *
     * @group Authentication
     * @unauthenticated
     *
     * @param  \App\Api\V1\Requests\SignUpRequest  $request
     * @param  \PHPOpenSourceSaver\JWTAuth\JWTAuth  $JWTAuth
     * @return \Illuminate\Http\JsonResponse
     */
    public function signUp(SignUpRequest $request, JWTAuth $JWTAuth)
    {
        $user = new User(array_merge($request->all(), ['role' => UserRole::User]));
        if(!$user->save()) {
            throw new HttpException(500);
        }

        $token = $JWTAuth->fromUser($user);
        return $this->authResponse($token, Response::HTTP_CREATED);
    }
}
