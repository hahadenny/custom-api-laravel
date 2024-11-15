<?php

namespace App\Api\V1\Requests\UserGroup;

use App\Api\V1\Requests\BatchDestroyGroupRequest;
use App\Models\User;
use App\Models\UserGroup;

class BatchDestroyRequest extends BatchDestroyGroupRequest
{
    protected function getItemClass(): string
    {
        return User::class;
    }

    protected function getGroupClass(): string
    {
        return UserGroup::class;
    }
}
