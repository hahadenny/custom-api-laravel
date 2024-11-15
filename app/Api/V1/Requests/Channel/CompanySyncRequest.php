<?php

namespace App\Api\V1\Requests\Channel;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;

class CompanySyncRequest extends FormRequest
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
            'assign_to_default' => 'sometimes|boolean',
            'channels' => 'array',
            'channels.*.name' => 'required|string',
            'channels.*.addresses' => 'required|array',
            'channels.*.type' => 'nullable|string|max:255',
            'channels.*.parent_name' => 'sometimes',
        ];
    }
}
