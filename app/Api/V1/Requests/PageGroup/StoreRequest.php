<?php

namespace App\Api\V1\Requests\PageGroup;

use App\Models\PageGroup;
use App\Rules\NodeDepthNewNode;
use App\Rules\NotInArray;
use App\Rules\UniqueNameAmongIds;
use App\Traits\Requests\DingoFormRequestAdapter;
use App\Traits\Rules\WorkflowRunLoggable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use DingoFormRequestAdapter, WorkflowRunLoggable;

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
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('page_groups')->where(function ($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('parent_id', $this->input('parent_id'))
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'color' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:1',
            'children_ids' => [
                'array',
                new UniqueNameAmongIds(PageGroup::class),
            ],
            'children_ids.*' => [
                'integer',
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
                new NotInArray('children_ids.*'),
                Rule::exists('page_groups', 'id')->where(function ($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
                (new NodeDepthNewNode(PageGroup::class))->setChildrenIdFieldName('children_ids'),
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
            'workflow_run_log_id' => $this->getWorkflowRunLogIdRules($authUser),
        ];
    }
}
