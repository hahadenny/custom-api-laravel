<?php

namespace App\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function rules()
    {
        $user = $this->user();
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'nullable|required_with:password|current_password:api',
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function authorize()
    {
        return true;
    }
}
