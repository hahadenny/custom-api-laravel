<?php

namespace App\Api\V1\Requests\UePreset;

use App\Models\UePreset;
use App\Rules\UniqueNameByIds;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BatchUpdateRequest extends FormRequest
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
            'ids' => [
                'required',
                'array',
                Rule::when($this->has('ue_preset_group_id'), [
                    UniqueNameByIds::make(UePreset::class)->where(function ($query) use ($authUser) {
                        /** @var \Illuminate\Database\Eloquent\Builder $query */
                        return $query
                            ->where('ue_preset_group_id', $this->input('ue_preset_group_id'))
                            ->where('company_id', $authUser->company_id);
                    }),
                ]),
            ],
            'ids.*' => [
                'integer',
                Rule::exists('ue_presets', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'ue_preset_group_id' => [
                'required',
                'integer',
                Rule::exists('ue_preset_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }
}
