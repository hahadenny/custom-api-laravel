<?php

namespace App\Api\V1\Controllers\Auth;

use App\Api\V1\Requests\LoginRequest;
use App\Http\Controllers\Controller;

use Dingo\Api\Exception\ValidationHttpException;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoginController extends Controller
{
    /**
     * Log the user in
     *
     * @group Authentication
     * @unauthenticated
     *
     * @param LoginRequest $request
     * @param JWTAuth $JWTAuth
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request, JWTAuth $JWTAuth)
    {
        $credentials = $request->only(['email', 'password']);

        $auth = Auth::guard();
        try {
            $token = $auth->attempt($credentials);
            if (!$token) {
                throw new ValidationHttpException(['password' => ['Incorrect email or password.']]);
            }
        } catch (JWTException $e) {
            // let the more useful exception through if we're in a testing environment
            throw config('app.env') !== 'local' ? new HttpException(500) : $e;
        }
        /** @var \App\Models\User $user */
        $user = $auth->user();
        if ($user && $user->company && !$user->company->is_active) {
            $auth->logout();
            throw new ValidationHttpException(['password' => ['Your company is not active. Please contact your Administrator.']]);
        }
        if ($user && !$user->isSuperAdmin() && !$user->company) {
            $auth->logout();
            throw new ValidationHttpException(['password' => ['Your company does not found. Please contact your Administrator.']]);
        }

        return $this->authResponse($token);
    }

    public function auth(JWTAuth $JWTAuth)
    {
        try {
            $token = Auth::guard('api')->login(Auth::guard('api-key')->user());
            return $this->authResponse($token);
        } catch (JWTException $e) {
            abort(401);
        }
    }
}
