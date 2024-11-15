<?php

namespace App\Api\V1\Requests\Template;

use App\Api\V1\Requests\BatchDestroyItemRequest;
use App\Models\Template;

class BatchDestroyRequest extends BatchDestroyItemRequest
{
    protected function getItemClass(): string
    {
        return Template::class;
    }
}
