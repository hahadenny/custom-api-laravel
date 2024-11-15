<?php

namespace App\Api\V1\Requests\ChannelGroup;

use App\Models\Channel;
use App\Models\ChannelGroup;
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
        /** @var \App\Models\ChannelGroup $group */
        $group = $this->route('channel_group');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('channel_groups')->where(function ($query) use ($authUser, $group) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('parent_id', $this->input('parent_id', $group->parent_id))
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                })->ignore($group),
            ],
            'sort_order' => 'nullable|integer|min:1',
            'children_ids' => [
                'array',
                UniqueNameByIds::make(ChannelGroup::class)->where(function ($query) use (
                    $authUser, $group
                ) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    return $query
                        ->where('parent_id', $group->id)
                        ->where('company_id', $authUser->company_id);
                }),
            ],
            'children_ids.*' => [
                'integer',
                'not_in:'.$group->id,
                Rule::exists('channel_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:'.$group->id,
                new NotInArray('children_ids.*'),
                Rule::exists('channel_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
                (new NodeDepth(ChannelGroup::class))
                    ->setMiddle($group)
                    ->setChildrenIdFieldName('children_ids'),
            ],
            'item_ids' => [
                'array',
                UniqueNameByIds::make(Channel::class)->where(function ($query) use (
                    $authUser, $group
                ) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    return $query
                        ->where('channel_group_id', $group->id)
                        ->where('company_id', $authUser->company_id);
                }),
            ],
            'item_ids.*' => [
                'integer',
                Rule::exists('channels', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'is_preview' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ];
    }
}
