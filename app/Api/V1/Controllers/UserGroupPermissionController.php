<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\UserGroupPermission\StoreRequest;
use App\Api\V1\Resources\ChannelResource;
use App\Api\V1\Resources\UserGroupPermissionResource;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\UserGroup;
use App\Services\ChannelService;
use App\Services\UserService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserGroupPermissionController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->authorizeResource(UserGroup::class, 'user_group');
    }

    /**
     * Display a listing of channel permissions.
     *
     * @group Channel Permissions
     *
     * @param  \App\Services\ChannelService  $channelService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\Company $company */
        $company = Auth::guard()->user()->company;
        return [
            'groups' => UserGroupPermissionResource::collection(
                $company->userGroups()->with(['channelPermissionables.permissions'])->get()
            ),
            'permissions' => [
                PermissionEnum::PLAY_PAGE,
                PermissionEnum::CONTINUE_PAGE
            ]
        ];
    }

    /**
     * Store a newly created channel.
     *
     * @group Channel
     *
     * @param  StoreRequest  $request
     * @param  \App\Services\UserService  $userService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, UserService $userService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $userService->storeGroupPermissions($authUser, $request->validated());
        return response()->noContent(Response::HTTP_OK);
    }
}
