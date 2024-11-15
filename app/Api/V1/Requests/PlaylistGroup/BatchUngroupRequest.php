<?php

namespace App\Api\V1\Requests\PlaylistGroup;

use App\Api\V1\Requests\BatchUngroupGroupRequest;
use App\Models\PlaylistGroup;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class BatchUngroupRequest extends BatchUngroupGroupRequest
{
    protected function getGroupClass(): string
    {
        return PlaylistGroup::class;
    }

    protected function addGroupRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');

        return $query->where('project_id', $project->id);
    }
}
