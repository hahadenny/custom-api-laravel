<?php

namespace App\Api\V1\Requests\FileMeta;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'id' => ['required'],
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required'],
            'tags' => ['nullable'],
            'description' => ['nullable']
        ];
    }
}
