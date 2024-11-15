<?php

namespace App\Api\V1\Requests\Workflow;

use App\Enums\WorkflowType;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'type' => [
                Rule::when($this->has('data'), [
                    'required',
                ]),
                new Enum(WorkflowType::class),
            ],
            'description' => 'nullable|string',
            'data' => [
                Rule::when($this->has('type'), [
                    'present',
                ]),
                'array',
            ],
        ];
    }
}
