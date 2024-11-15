<?php

namespace App\Api\V1\Requests\Workflow;

use App\Enums\WorkflowType;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RunFilterStepsRequest extends FormRequest
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
            'type' => [
                'required',
                new Enum(WorkflowType::class),
            ],
            'steps' => 'present|array',
            'steps.*' => 'sometimes',
            'steps.*.type' => 'in:filter_step',
        ];
    }
}
