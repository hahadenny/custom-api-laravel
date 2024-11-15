<?php

namespace App\Api\V1\Requests\Template;

use App\Api\V1\Requests\BatchUpdateItemRequest;
use App\Models\Template;
use App\Models\TemplateGroup;

class BatchUpdateRequest extends BatchUpdateItemRequest
{
    protected function getItemClass(): string
    {
        return Template::class;
    }

    protected function getGroupClass(): string
    {
        return TemplateGroup::class;
    }

    protected function getGroupIdField(): string
    {
        return 'template_group_id';
    }
}
