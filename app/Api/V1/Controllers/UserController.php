<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\User\BatchDestroyRequest;
use App\Api\V1\Requests\User\BatchUpdateRequest;
use App\Api\V1\Requests\User\StoreRequest;
use App\Api\V1\Requests\User\UpdateRequest;
use App\Api\V1\Resources\UserListingResource;
use App\Api\V1\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    use AuthorizesBatchRequests;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.auth', []);
        $this->authorizeResource(User::class, 'user');
    }

    /**
     * Display a listing of users.
     *
     * @group User
     *
     * @param  \App\Services\UserService  $userService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(UserService $userService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return UserListingResource::collection($userService->listing($authUser));
    }

    /**
     * Store a newly created user.
     *
     * @group User
     *
     * @param  \App\Api\V1\Requests\User\StoreRequest  $request
     * @param  \App\Services\UserService  $userService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, UserService $userService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new UserResource($userService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified user.
     *
     * @group User
     *
     * @param  \App\Models\User  $user
     * @return \App\Api\V1\Resources\UserResource
     */
    public function show(User $user)
    {
        return new UserResource($user);
    }

    /**
     * Update the specified user.
     *
     * @group User
     *
     * @param  \App\Api\V1\Requests\User\UpdateRequest  $request
     * @param  \App\Models\User  $user
     * @param  \App\Services\UserService  $userService
     * @return \App\Api\V1\Resources\UserResource
     */
    public function update(UpdateRequest $request, User $user, UserService $userService)
    {
        $data = $request->validated();
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        if ($authUser->id === $user->id || isset($data['permissions']['user'])) {
            unset($data['permissions']);
        }
        return new UserResource($userService->update($user, $data));
    }

    /**
     * Update the specified users.
     *
     * @group User
     *
     * @param  \App\Api\V1\Requests\User\BatchUpdateRequest  $request
     * @param  \App\Services\UserService  $userService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, UserService $userService)
    {
        return UserResource::collection($userService->batchUpdate($request->validated()));
    }

    /**
     * Remove the specified user.
     *
     * @group User
     *
     * @param  \App\Models\User  $user
     * @param  \App\Services\UserService  $userService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(User $user, UserService $userService)
    {
        $userService->delete($user);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified users.
     *
     * @group User
     *
     * @param  \App\Api\V1\Requests\User\BatchDestroyRequest  $request
     * @param  \App\Services\UserService  $userService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, UserService $userService)
    {
        $userService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
