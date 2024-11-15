<?php

namespace App\Api\V1\Requests\TemplateGroup;

use App\Api\V1\Requests\BatchUngroupGroupRequest;
use App\Models\TemplateGroup;

class BatchUngroupRequest extends BatchUngroupGroupRequest
{
    protected function getGroupClass(): string
    {
        return TemplateGroup::class;
    }
}
