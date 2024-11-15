<?php

namespace App\Api\V1\Requests\UePresetGroup;

use App\Api\V1\Requests\BatchUpdateGroupRequest;
use App\Models\UePreset;
use App\Models\UePresetGroup;

class BatchUpdateRequest extends BatchUpdateGroupRequest
{
    protected function getItemClass(): string
    {
        return UePreset::class;
    }

    protected function getGroupClass(): string
    {
        return UePresetGroup::class;
    }

    protected function getItemGroupIdColumn(): string
    {
        return 'ue_preset_group_id';
    }
}
