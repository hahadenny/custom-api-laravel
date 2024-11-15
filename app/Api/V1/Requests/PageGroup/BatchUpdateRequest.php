<?php

namespace App\Api\V1\Requests\PageGroup;

use App\Api\V1\Requests\BatchUpdateGroupRequest;
use App\Models\Page;
use App\Models\PageGroup;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class BatchUpdateRequest extends BatchUpdateGroupRequest
{
    protected function getItemClass(): string
    {
        return Page::class;
    }

    protected function getGroupClass(): string
    {
        return PageGroup::class;
    }

    protected function getItemGroupIdColumn(): string
    {
        return 'page_group_id';
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            'color' => 'nullable|string',
        ]);
    }

    protected function addItemRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        /** @var \App\Models\Playlist $playlist */
        $playlist = $this->route('playlist');

        return $query->where(function ($q) use ($playlist) {
            /** @var \Illuminate\Database\Query\Builder $q */
            $q->whereIn('id', $playlist->pages()->select(['pages.id']));
        });
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
