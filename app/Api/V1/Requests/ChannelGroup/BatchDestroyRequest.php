<?php

namespace App\Api\V1\Requests\ChannelGroup;

use App\Api\V1\Requests\BatchDestroyGroupRequest;
use App\Models\Channel;
use App\Models\ChannelGroup;

class BatchDestroyRequest extends BatchDestroyGroupRequest
{
    protected function getItemClass(): string
    {
        return Channel::class;
    }

    protected function getGroupClass(): string
    {
        return ChannelGroup::class;
    }
}
