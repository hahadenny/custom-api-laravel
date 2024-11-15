<?php

namespace App\Api\V1\Requests\Channel;

use App\Models\Schedule\States\PlayoutState;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\ModelStates\Validation\ValidStateRule;

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
        /** @var \App\Models\Channel $channel */
        $channel = $this->route('channel');
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
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('channels')->where(function ($query) use ($authUser, $channel) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('channel_group_id', $this->input('channel_group_id', $channel->channel_group_id))
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                })->ignore($channel),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('channels', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('parent_id')
                    ->whereNot('id', $channel->id)
                    ->whereNull('deleted_at'),
            ],
            'addresses'  => 'nullable|array',
            'stream_type' => 'nullable|string',
            'stream_url' => 'nullable|string',
            'is_default'  => 'nullable|boolean',
            'is_preview' => 'sometimes|boolean',
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:1',
            'status' => ValidStateRule::make(PlayoutState::class)->nullable(),
            // JS will not always send a timezone that is valid according to the `timezone_identifiers_list` in PHP
            // so validate that it is a string instead, and check timezone against timezone_identifiers_list if needed later
            'user_timezone' => ['string', 'nullable'],
        ];
    }
}
