<?php

namespace App\Api\V1\Requests\TemplateGroup;

use App\Api\V1\Requests\BatchUpdateGroupRequest;
use App\Models\Template;
use App\Models\TemplateGroup;

class BatchUpdateRequest extends BatchUpdateGroupRequest
{
    protected function getItemClass(): string
    {
        return Template::class;
    }

    protected function getGroupClass(): string
    {
        return TemplateGroup::class;
    }

    protected function getItemGroupIdColumn(): string
    {
        return 'template_group_id';
    }
}
