<?php

namespace App\Api\V1\Requests\Playlist;

use App\Api\V1\Requests\BatchDestroyItemRequest;
use App\Models\Playlist;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class BatchDestroyRequest extends BatchDestroyItemRequest
{
    protected function getItemClass(): string
    {
        return Playlist::class;
    }

    protected function addItemRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');

        return $query->where('project_id', $project->id);
    }
}
