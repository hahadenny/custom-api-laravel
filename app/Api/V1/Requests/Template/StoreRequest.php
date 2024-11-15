<?php

namespace App\Api\V1\Requests\Template;

use App\Enums\TemplateType;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreRequest extends FormRequest
{
    use DingoFormRequestAdapter;

    protected function prepareForValidation()
    {
        $emptyStringsToNullFields = [
            'template_group_id',
            'ue_preset_asset_id',
            'name',
            'type',
            'engine',
            'color',
            'sort_order',
            'default_duration',
        ];

        foreach ($emptyStringsToNullFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

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
            'template_group_id' => [
                'nullable',
                'integer',
                Rule::exists('template_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'ue_preset_asset_id' => [
                'nullable',
                'integer',
                Rule::exists('ue_preset_assets', 'id')->where(function ($query) use ($authUser) {
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
                Rule::unique('templates')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('template_group_id', $this->input('template_group_id'))
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'type' => [
                new Enum(TemplateType::class),
            ],
            'engine' => 'nullable|string',
            'color' => 'nullable|string',
            'preset' => ['nullable', 'string'],
            'data' => 'required|array',
            'sort_order' => 'nullable|integer|min:1',
            'default_duration' => ['nullable', 'string'],
        ];
    }
}
