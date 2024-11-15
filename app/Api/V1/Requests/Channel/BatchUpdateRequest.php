<?php

namespace App\Api\V1\Requests\Channel;

use App\Api\V1\Requests\BatchUpdateItemRequest;
use App\Models\Channel;
use App\Models\ChannelGroup;

class BatchUpdateRequest extends BatchUpdateItemRequest
{
    protected function getItemClass(): string
    {
        return Channel::class;
    }

    protected function getGroupClass(): string
    {
        return ChannelGroup::class;
    }

    protected function getGroupIdField(): string
    {
        return 'channel_group_id';
    }
}
