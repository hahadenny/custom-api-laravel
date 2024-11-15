<?php

namespace App\Api\V1\Requests\Page;

use App\Api\V1\Requests\BatchDestroyItemRequest;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class BatchDestroyRequest extends BatchDestroyItemRequest
{
    protected function getItemClass(): string
    {
        return Page::class;
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
}
