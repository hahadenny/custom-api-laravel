<?php

namespace App\Api\V1\Requests\ChannelGroup;

use App\Api\V1\Requests\BatchUpdateGroupRequest;
use App\Models\Channel;
use App\Models\ChannelGroup;

class BatchUpdateRequest extends BatchUpdateGroupRequest
{
    protected function getItemClass(): string
    {
        return Channel::class;
    }

    protected function getGroupClass(): string
    {
        return ChannelGroup::class;
    }

    protected function getItemGroupIdColumn(): string
    {
        return 'channel_group_id';
    }
}
