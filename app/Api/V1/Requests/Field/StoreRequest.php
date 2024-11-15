<?php

namespace App\Api\V1\Requests\Field;

use App\Enums\FieldType;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'page_id' => [
                'required',
                'integer',
                Rule::exists('pages', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fields')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('page_id', $this->input('page_id'))
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'ue_preset_display_name' => 'required|string|max:255',
            'type' => [
                new Enum(FieldType::class),
            ],
            'value' => 'nullable|string',
        ];
    }
}
