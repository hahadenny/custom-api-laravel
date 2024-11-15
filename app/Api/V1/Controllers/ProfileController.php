<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\UpdateProfileRequest;
use App\Api\V1\Resources\ProfileResource;
use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.auth', []);
    }

    /**
     * Get the authenticated User
     *
     * @group User
     *
     * @return \App\Api\V1\Resources\ProfileResource
     */
    public function show()
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard()->user();
        $user->load(['userProject.project']);
        return new ProfileResource($user);
    }

    /**
     * Update the authenticated User
     *
     * @group User
     * @param UpdateProfileRequest $request
     * @param UserService $userService
     *
     * @return \App\Api\V1\Resources\ProfileResource
     */
    public function update(UpdateProfileRequest $request, UserService $userService)
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard()->user();
        $userService->update($user, $request->validated());
        return new ProfileResource($user);
    }

}
