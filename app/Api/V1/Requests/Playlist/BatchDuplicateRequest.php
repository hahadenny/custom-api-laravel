<?php

namespace App\Api\V1\Requests\Playlist;

use App\Api\V1\Requests\BatchDuplicateItemRequest;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class BatchDuplicateRequest extends BatchDuplicateItemRequest
{
    protected function getItemClass(): string
    {
        return Playlist::class;
    }

    protected function getGroupClass(): string
    {
        return PlaylistGroup::class;
    }

    protected function getGroupIdField(): string
    {
        return 'playlist_group_id';
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
