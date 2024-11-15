<?php

namespace App\Api\V1\Requests\User;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BatchDestroyRequest extends FormRequest
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

        $existsRule = Rule::exists('users', 'id')
            ->whereNot('id', $authUser->id)
            ->whereNull('deleted_at');

        if (! $authUser->isSuperAdmin()) {
            $existsRule->where('company_id', $authUser->company_id);
        }

        return [
            'ids' => 'required|array',
            'ids.*' => [
                'integer',
                $existsRule,
            ],
        ];
    }
}
