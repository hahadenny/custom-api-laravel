<?php

namespace App\Api\V1\Requests\ChannelGroup;

use App\Api\V1\Requests\BatchDuplicateGroupRequest;
use App\Models\Channel;
use App\Models\ChannelGroup;

class BatchDuplicateRequest extends BatchDuplicateGroupRequest
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
