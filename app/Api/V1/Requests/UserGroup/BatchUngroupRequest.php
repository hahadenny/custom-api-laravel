<?php

namespace App\Api\V1\Requests\UserGroup;

use App\Api\V1\Requests\BatchUngroupGroupRequest;
use App\Models\UserGroup;

class BatchUngroupRequest extends BatchUngroupGroupRequest
{
    protected function getGroupClass(): string
    {
        return UserGroup::class;
    }
}
