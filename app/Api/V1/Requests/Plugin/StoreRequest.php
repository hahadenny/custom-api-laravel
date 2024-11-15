<?php

namespace App\Api\V1\Requests\Plugin;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

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

    public function rules()
    {
        return [
            'file' => [
                'required',
                File::types(['zip'])->max(900 * 1000),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'version' => [
                'required',
                'string',
                'max:255',
            ],
            'internal' => [
                'required',
                'boolean',
            ]
        ];
    }
}
