<?php

namespace App\Api\V1\Requests\User;

use App\Enums\UserRole;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

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

        $companyRules = [];
        $roleRules = [];
        $groupIdRules = [];

        if ($authUser->isSuperAdmin()) {
            if ($this->input('role') !== UserRole::SuperAdmin->value) {
                $companyRules['company_id'] = [
                    'required',
                    'integer',
                    Rule::exists('companies', 'id')->whereNull('deleted_at'),
                ];
            }

            $roleRules['role'] = [
                'required',
                new Enum(UserRole::class),
            ];
        } else {
            $roles = [];

            if ($authUser->isAdmin()) {
                $roles = [
                    UserRole::Admin->value,
                    UserRole::User->value,
                ];
            }

            $roleRules['role'] = [
                'required',
                new Enum(UserRole::class),
                Rule::in($roles),
            ];

            $groupIdRules['user_group_id'] = [
                'nullable',
                'integer',
                Rule::exists('user_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ];
        }

        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            ...$roleRules,
            'password' => ['required', Password::defaults()],
            ...$companyRules,
            'settings' => 'array',
            ...$groupIdRules,
            'channel_group_ids' => 'sometimes|array',
        ];
    }
}
