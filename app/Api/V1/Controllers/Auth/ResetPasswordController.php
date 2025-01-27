<?php

namespace App\Api\V1\Controllers\Auth;

use App\Api\V1\Requests\ResetPasswordRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Config;
use Illuminate\Support\Facades\Password;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResetPasswordController extends Controller
{
    /**
     * Reset a password.
     *
     * @group Authentication
     *
     * @param  \App\Api\V1\Requests\ResetPasswordRequest  $request
     * @param  \PHPOpenSourceSaver\JWTAuth\JWTAuth  $JWTAuth
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request, JWTAuth $JWTAuth)
    {
        $response = $this->broker()->reset(
            $this->credentials($request), function ($user, $password) {
                $this->reset($user, $password);
            }
        );

        if ($response !== Password::PASSWORD_RESET) {
            throw new HttpException(500);
        }

        $user = User::where('email', $request->get('email'))->first();

        return $this->authResponse($JWTAuth->fromUser($user));
    }

    /**
     * Get the broker to be used during password reset.
     *
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker()
    {
        return Password::broker();
    }

    /**
     * Get the password reset credentials from the request.
     *
     * @param  ResetPasswordRequest  $request
     * @return array
     */
    protected function credentials(ResetPasswordRequest $request)
    {
        return $request->only(
            'email', 'password', 'password_confirmation', 'token'
        );
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     */
    protected function reset($user, $password)
    {
        $user->password = $password;
        $user->save();
    }
}
