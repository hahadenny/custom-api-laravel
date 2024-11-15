<?php

namespace App\Api\V1\Requests\Template;

use App\Models\Template;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BatchExportRequest extends FormRequest
{
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
            'ids' => 'array',
            'ids.*' => [
                'integer',
                Rule::exists('templates', 'id')
                    ->where('company_id', $authUser->company_id)
//                    ->where(function (Builder $q) {
//                        $q
//                            ->whereNull('preset')
//                            ->orWhereNot('preset', Template::PRESET_D3);
//                    })
                    ->whereNull('deleted_at'),
            ],
            'group_ids' => 'array',
            'group_ids.*' => [
                'integer',
                Rule::exists('template_groups', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
