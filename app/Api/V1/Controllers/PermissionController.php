<?php

namespace App\Api\V1\Controllers;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Permission::class, 'permission');
    }

    /**
     * Display a listing of permissions.
     *
     * @group Permission
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return response()->json(PermissionEnum::cases());
    }

}
