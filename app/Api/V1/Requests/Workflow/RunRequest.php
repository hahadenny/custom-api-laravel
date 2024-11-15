<?php

namespace App\Api\V1\Requests\Workflow;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RunRequest extends FormRequest
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
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'playlist_id' => [
                'nullable',
                'integer',
                Rule::exists('playlists', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at'),
            ],
            'page_ids' => 'array',
            'page_ids.*' => [
                'integer',
                Rule::exists('pages', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
