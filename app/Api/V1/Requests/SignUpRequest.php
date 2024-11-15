<?php

namespace App\Api\V1\Requests;

use Dingo\Api\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SignUpRequest extends FormRequest
{
    public function rules()
    {
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::defaults()],
            'company_id' => 'required|integer|exists:companies,id',
        ];
    }

    public function authorize()
    {
        return true;
    }
}
