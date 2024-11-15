<?php

namespace App\Api\V1\Requests\Channel;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
            'channel_group_id' => [
                'nullable',
                'integer',
                Rule::exists('channel_groups', 'id')->where(function ($query) use ($authUser) {
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
                Rule::unique('channels')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('channel_group_id', $this->input('channel_group_id'))
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('channels', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
            ],
            'addresses' => 'nullable|array',
            'stream_type' => 'nullable|string',
            'stream_url' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
            'is_preview' => 'sometimes|boolean',
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:1',
            'status' => 'nullable|string',
        ];
    }
}