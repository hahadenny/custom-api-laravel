<?php

namespace App\Api\V1\Requests\Channel;

use App\Api\V1\Requests\BatchDestroyItemRequest;
use App\Models\Channel;

class BatchDestroyRequest extends BatchDestroyItemRequest
{
    protected function getItemClass(): string
    {
        return Channel::class;
    }
}
