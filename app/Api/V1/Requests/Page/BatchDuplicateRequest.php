<?php

namespace App\Api\V1\Requests\Page;

use App\Api\V1\Requests\BatchDuplicateItemRequest;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\PlaylistListing;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class BatchDuplicateRequest extends BatchDuplicateItemRequest
{
    protected function getItemClass(): string
    {
        return Page::class;
    }

    protected function getGroupClass(): string
    {
        return PageGroup::class;
    }

    protected function getGroupIdField(): string
    {
        return 'page_group_id';
    }

    protected function addParentScopeRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        /** @var \App\Models\Playlist $playlist */
        $playlist = $this->route('playlist');

        return $query->where('playlist_id', $playlist->id);
    }

    protected function addGlobalScopeRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        /** @var \App\Models\Playlist $playlist */
        $playlist = $this->route('playlist');

        $pageIdsQuery = PlaylistListing::query()
            ->select(['playlistable_id'])
            ->whereIn('playlist_id', $playlist->project->playlists()->select(['playlists.id']))
            ->where('playlistable_type', Page::class);

        $query->where(function ($q) use ($pageIdsQuery) {
            /** @var \Illuminate\Database\Query\Builder $q */
            return $q->whereIn('id', $pageIdsQuery);
        });

        return $query;
    }
}
