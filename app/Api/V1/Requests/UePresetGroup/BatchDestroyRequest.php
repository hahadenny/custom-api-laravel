<?php

namespace App\Api\V1\Requests\UePresetGroup;

use App\Api\V1\Requests\BatchDestroyGroupRequest;
use App\Models\UePreset;
use App\Models\UePresetGroup;

class BatchDestroyRequest extends BatchDestroyGroupRequest
{
    protected function getItemClass(): string
    {
        return UePreset::class;
    }

    protected function getGroupClass(): string
    {
        return UePresetGroup::class;
    }
}
