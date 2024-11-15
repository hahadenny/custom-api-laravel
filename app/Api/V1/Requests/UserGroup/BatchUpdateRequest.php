<?php

namespace App\Api\V1\Requests\UserGroup;

use App\Enums\UserRole;
use App\Models\UserGroup;
use App\Rules\NodeDepth;
use App\Rules\NotInArray;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BatchUpdateRequest extends FormRequest
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
            'ids' => 'array',
            'ids.*' => [
                'integer',
                Rule::exists('user_groups', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at'),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                new NotInArray('ids.*'),
                Rule::exists('user_groups', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at'),
                (new NodeDepth(UserGroup::class))->setChildrenIdFieldName('ids'),
            ],
            // These items will be saved to the "parent_id" group.
            'item_ids' => 'array',
            'item_ids.*' => [
                'integer',
                Rule::exists('users', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereIn('role', [UserRole::User, UserRole::Admin])
                    ->whereNull('deleted_at'),
            ],
            'sort_order' => 'nullable|integer|min:1',
        ];
    }
}
