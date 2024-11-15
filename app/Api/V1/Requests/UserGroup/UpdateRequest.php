<?php

namespace App\Api\V1\Requests\UserGroup;

use App\Models\UserGroup;
use App\Rules\NodeDepth;
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
        /** @var \App\Models\UserGroup $group */
        $group = $this->route('user_group');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('user_groups')->where(function ($query) use ($authUser, $group) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('parent_id', $this->input('parent_id', $group->parent_id))
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                })->ignore($group),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:'.$group->id,
                Rule::exists('user_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
                (new NodeDepth(UserGroup::class))->setChildrenIds([$group->id]),
            ],
        ];
    }
}
