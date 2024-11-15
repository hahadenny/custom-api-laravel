<?php

namespace App\Api\V1\Requests\TemplateGroup;

use App\Api\V1\Requests\BatchDuplicateGroupRequest;
use App\Models\Template;
use App\Models\TemplateGroup;

class BatchDuplicateRequest extends BatchDuplicateGroupRequest
{
    protected function getItemClass(): string
    {
        return Template::class;
    }

    protected function getGroupClass(): string
    {
        return TemplateGroup::class;
    }
}
