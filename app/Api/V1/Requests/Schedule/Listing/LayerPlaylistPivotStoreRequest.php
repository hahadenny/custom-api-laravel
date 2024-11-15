<?php

namespace App\Api\V1\Requests\Schedule\Listing;

use App\Models\ChannelLayer;
use App\Models\Playlist;
use App\Traits\Requests\DingoFormRequestAdapter;
use App\Traits\Requests\ScheduleListingRequestTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LayerPlaylistPivotStoreRequest extends FormRequest
{
    use DingoFormRequestAdapter, ScheduleListingRequestTrait;

    protected function additionalRules() : array
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $childTable = (new Playlist())->getTable();
        $parentTable = (new ChannelLayer())->getTable();

        return [
            'scheduleable_id' => [
                'required',
                'integer',
                // this child must be unique to its layer
                Rule::unique('schedule_listings')->where(function($query) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('scheduleable_id', $this->input('scheduleable_id'))
                        ->where('parent_id', $this->input('parent_id'))
                        ->whereNull('deleted_at');
                }),
                Rule::exists($childTable, 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at'),
            ],
            'scheduleable_type' => [
                'required',
                'string',
            ],
        ];
    }

    protected function additionalMessages() : array
    {
        return [
            'scheduleable_id.unique' => 'This item already exists in the layer.',
        ];
    }

}
