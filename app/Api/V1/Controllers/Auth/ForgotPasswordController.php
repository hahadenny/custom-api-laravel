<?php

namespace App\Api\V1\Controllers\Auth;

use App\Api\V1\Requests\ForgotPasswordRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForgotPasswordController extends Controller
{
    /**
     * Recover a password.
     *
     * @group Authentication
     * @unauthenticated
     *
     * @param  \App\Api\V1\Requests\ForgotPasswordRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetEmail(ForgotPasswordRequest $request)
    {
        $data = $request->only(['email']);
        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['status' => 'ok'], 200);
        }

        $broker = $this->getPasswordBroker();
        $sendingResponse = $broker->sendResetLink($data);

        if ($sendingResponse !== Password::RESET_LINK_SENT) {
            throw new HttpException(500);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Get the broker to be used during password reset.
     *
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    private function getPasswordBroker()
    {
        return Password::broker();
    }
}
