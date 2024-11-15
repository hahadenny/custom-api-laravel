<?php

namespace App\Api\V1\Requests\Company;

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
        /** @var \App\Models\Company $company */
        $company = $this->route('company');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        if (!$authUser->isSuperAdmin()) {
            return [
                'ue_url' => 'sometimes|required|string|max:255',
                'settings' => 'nullable|array',
            ];
        }

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('companies')->whereNull('deleted_at')->ignore($company),
            ],
            'description' => 'nullable|string',
            'ue_url' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'settings' => 'nullable|array',
        ];
    }
}
