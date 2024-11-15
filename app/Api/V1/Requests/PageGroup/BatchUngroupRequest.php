<?php

namespace App\Api\V1\Requests\PageGroup;

use App\Api\V1\Requests\BatchUngroupGroupRequest;
use App\Models\PageGroup;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class BatchUngroupRequest extends BatchUngroupGroupRequest
{
    protected function getGroupClass(): string
    {
        return PageGroup::class;
    }

    protected function addGroupRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        /** @var \App\Models\Playlist $playlist */
        $playlist = $this->route('playlist');

        return $query->where('playlist_id', $playlist->id);
    }
}
