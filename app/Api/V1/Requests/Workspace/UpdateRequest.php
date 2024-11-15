<?php

namespace App\Api\V1\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
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
        /** @var \App\Models\Workspace $workspace */
        $workspace = $this->route('workspace');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'filled',
                'string',
                'max:255',
                Rule::unique('workspaces')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where(['company_id' => $authUser->company_id, 'user_id' => $authUser->id])
                        ->whereNull('deleted_at');
                })->ignore($workspace),
            ],
            'is_active' => 'boolean',
            'auto_save' => 'boolean',
            'layout' => 'array',
        ];
    }
}
