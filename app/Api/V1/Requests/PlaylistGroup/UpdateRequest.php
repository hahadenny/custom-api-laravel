<?php

namespace App\Api\V1\Requests\PlaylistGroup;

use App\Models\Playlist;
use App\Models\PlaylistGroup;
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
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        /** @var \App\Models\PlaylistGroup $group */
        $group = $this->route('playlist_group');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('playlist_groups')->where(function ($query) use ($authUser, $project, $group) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('parent_id', $this->input('parent_id', $group->parent_id))
                        ->where('project_id', $project->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                })->ignore($group),
            ],
            'sort_order' => 'nullable|integer|min:1',
            'children_ids' => [
                'array',
                UniqueNameByIds::make(PlaylistGroup::class)->where(function ($query) use (
                    $authUser, $project, $group
                ) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    return $query
                        ->where('parent_id', $group->id)
                        ->where('project_id', $project->id)
                        ->where('company_id', $authUser->company_id);
                }),
            ],
            'children_ids.*' => [
                'integer',
                'not_in:'.$group->id,
                Rule::exists('playlist_groups', 'id')->where(function ($query) use ($authUser, $project) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('project_id', $project->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:'.$group->id,
                new NotInArray('children_ids.*'),
                Rule::exists('playlist_groups', 'id')->where(function ($query) use ($authUser, $project) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('project_id', $project->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
                (new NodeDepth(PlaylistGroup::class))
                    ->setMiddle($group)
                    ->setChildrenIdFieldName('children_ids'),
            ],
            'item_ids' => [
                'array',
                UniqueNameByIds::make(Playlist::class)->where(function ($query) use (
                    $authUser, $project, $group
                ) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    return $query
                        ->where('playlist_group_id', $group->id)
                        ->where('project_id', $project->id)
                        ->where('company_id', $authUser->company_id);
                }),
            ],
            'item_ids.*' => [
                'integer',
                Rule::exists('playlists', 'id')->where(function ($query) use ($authUser, $project) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('project_id', $project->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }
}
