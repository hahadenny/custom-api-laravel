<?php

namespace App\Api\V1\Requests\FileMeta;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;

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
            'id' => ['required'], // TODO how can request have this on a store request???
            'file_name' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'], // just the file_name again
            // 'source' => ['required'],
            'tags' => ['nullable'],
            'description' => ['nullable']
        ];
    }
}
