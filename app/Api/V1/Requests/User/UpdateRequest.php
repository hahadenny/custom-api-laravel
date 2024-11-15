<?php

namespace App\Api\V1\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

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
        /** @var \App\Models\User $user */
        $user = $this->route('user');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $companyRules = [];
        $groupIdRules = [];

        if ($authUser->isSuperAdmin()) {
            $role = $this->has('role') ? $this->input('role') : $user->role->value;

            if ($role !== UserRole::SuperAdmin->value) {
                $companyRules['company_id'] = [];

                if (is_null($user->company_id)) {
                    $companyRules['company_id'][] = 'required';
                }

                $companyRules['company_id'] = array_merge($companyRules['company_id'], [
                    'integer',
                    Rule::exists('companies', 'id')->whereNull('deleted_at'),
                    $this->makeLastAdminRule($user),
                ]);
            }
        } else {
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
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users')->ignore($user),
            ],
            ...$this->makeRoleRules($authUser, $user),
            'current_password' => [
                Rule::when($user->id === $authUser->id, [
                    'nullable',
                    'required_with:password',
                    'current_password:api',
                ]),
            ],
            'password' => ['sometimes', 'exclude_if:password,null', 'required', Password::defaults()],
            ...$companyRules,
            'settings' => 'array',
            'permissions' => 'sometimes|array',
            'channel_group_ids' => 'sometimes|array',
            ...$groupIdRules,
        ];
    }

    protected function makeRoleRules(User $authUser, User $user): array
    {
        // Changing the role of yourself is not allowed.
        if ($user->id === $authUser->id) {
            return [];
        }

        if ($authUser->isUser()) {
            return [];
        }

        $roleRules = [
            new Enum(UserRole::class),
        ];

        if (! $authUser->isSuperAdmin()) {
            $roles = [];

            if ($authUser->isAdmin()) {
                $roles = [
                    UserRole::User->value,
                    UserRole::Admin->value,
                ];
            }

            $roleRules[] = Rule::in($roles);
        }

        $roleRules[] = $this->makeLastAdminRule($user);

        return ['role' => $roleRules];
    }

    protected function makeLastAdminRule(User $user): callable
    {
        return function (string $attribute, mixed $value, callable $fail) use ($user): void {
            if (! $user->isAdmin()) {
                return;
            }

            if ($attribute === 'role') {
                if (! $this->has('role') || $this->input('role') === $user->role->value) {
                    return;
                }
            } elseif ($attribute === 'company_id') {
                if (! $this->has('company_id') || $this->input('company_id') === $user->company_id) {
                    return;
                }
            }

            $isLastAdmin = User::query()
                ->where('company_id', $user->company_id)
                ->where('role', $user->role->value)
                ->whereKeyNot($user)
                ->doesntExist();

            if ($isLastAdmin) {
                $fail(trans('messages.validation.last_admin_in_company'));
            }
        };
    }
}
