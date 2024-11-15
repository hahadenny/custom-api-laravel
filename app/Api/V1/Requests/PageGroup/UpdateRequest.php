<?php

namespace App\Api\V1\Requests\PageGroup;

use App\Models\PageGroup;
use App\Rules\NodeDepth;
use App\Rules\NotInArray;
use App\Rules\UniqueNameByIds;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /** @var \App\Models\Playlist $playlist */
        $playlist = $this->route('playlist');
        /** @var \App\Models\PageGroup $group */
        $group = $this->route('page_group');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('page_groups')->where(function ($query) use ($authUser, $playlist, $group) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('parent_id', $this->input('parent_id', $group->parent_id))
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                })->ignore($group),
            ],
            'color' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:1',
            'children_ids' => [
                'array',
                UniqueNameByIds::make(PageGroup::class)->where(function ($query) use (
                    $authUser, $playlist, $group
                ) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    return $query
                        ->where('parent_id', $group->id)
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id);
                }),
            ],
            'children_ids.*' => [
                'integer',
                'not_in:'.$group->id,
                Rule::exists('page_groups', 'id')->where(function ($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:'.$group->id,
                new NotInArray('children_ids.*'),
                Rule::exists('page_groups', 'id')->where(function ($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
                (new NodeDepth(PageGroup::class))
                    ->setMiddle($group)
                    ->setChildrenIdFieldName('children_ids'),
            ],
            'item_ids' => [
                'array',
            ],
            'item_ids.*' => [
                'integer',
                Rule::exists('pages', 'id')->where(function ($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($playlist) {
                            /** @var \Illuminate\Database\Query\Builder $q */
                            return $q->whereIn('id', $playlist->pages()->select(['pages.id']));
                        });
                }),
            ],
        ];
    }
}
