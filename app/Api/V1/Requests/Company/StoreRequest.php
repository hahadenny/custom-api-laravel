<?php

namespace App\Api\V1\Requests\Company;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('companies')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string',
            'cluster_id' => 'nullable',
            'ue_url' => 'required_if:cluster_id,null|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'password' => ['required', Password::defaults()],
        ];
    }
}
