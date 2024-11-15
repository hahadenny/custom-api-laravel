<?php

namespace App\Api\V1\Requests\PlaylistGroup;

use App\Api\V1\Requests\BatchDuplicateGroupRequest;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class BatchDuplicateRequest extends BatchDuplicateGroupRequest
{
    protected function getItemClass(): string
    {
        return Playlist::class;
    }

    protected function getGroupClass(): string
    {
        return PlaylistGroup::class;
    }

    protected function addParentScopeRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');

        return $query->where('project_id', $project->id);
    }

    protected function addGlobalScopeRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');

        return $query->where('project_id', $project->id);
    }
}
