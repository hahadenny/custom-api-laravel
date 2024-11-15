<?php

namespace App\Api\V1\Requests\ChannelGroup;

use App\Api\V1\Requests\BatchUngroupGroupRequest;
use App\Models\ChannelGroup;

class BatchUngroupRequest extends BatchUngroupGroupRequest
{
    protected function getGroupClass(): string
    {
        return ChannelGroup::class;
    }
}
