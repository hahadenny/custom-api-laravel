<?php

namespace App\Api\V1\Requests\PageGroup;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PlaySequenceRequest extends FormRequest
{
    use DingoFormRequestAdapter;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var \App\Models\PageGroup $group */
        $group = $this->route('page_group');
        $pages = $group->listingPages;

        $this->merge([
             'pages'    => $pages->all(),
             'item_ids' => $pages->pluck('id')->all(),
         ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        /** @var \App\Models\Playlist $playlist */
        $playlist = $this->route('playlist');

        return [
            'sort_order' => 'nullable|integer|min:1',
            'pages'      => ['array'],
            'item_ids'   => [
                'array',
            ],
            'item_ids.*' => [
                'integer',
                // make sure all pages in the group exist in the playlist and have a channel assigned
                Rule::exists('pages', 'id')->where(function($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                        ->where(function($q) use ($playlist) {
                            /** @var \Illuminate\Database\Query\Builder $q */
                            return $q->whereIn('id', $playlist->pages()->select(['pages.id']));
                        })
                        ->where(function($q){
                            $q->whereNotNull('channel_id')->orWhereNotNull('channel_entity_id');
                        });
                }),
            ],
        ];
    }
}
