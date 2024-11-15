<?php

namespace App\Api\V1\Requests\ChannelGroup;

use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Rules\NodeDepthNewNode;
use App\Rules\NotInArray;
use App\Rules\UniqueNameAmongIds;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
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
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('channel_groups')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('parent_id', $this->input('parent_id'))
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'sort_order' => 'nullable|integer|min:1',
            'children_ids' => [
                'array',
                new UniqueNameAmongIds(ChannelGroup::class),
            ],
            'children_ids.*' => [
                'integer',
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
                new NotInArray('children_ids.*'),
                Rule::exists('channel_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
                (new NodeDepthNewNode(ChannelGroup::class))->setChildrenIdFieldName('children_ids'),
            ],
            'item_ids' => [
                'array',
                new UniqueNameAmongIds(Channel::class),
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
        ];
    }
}
