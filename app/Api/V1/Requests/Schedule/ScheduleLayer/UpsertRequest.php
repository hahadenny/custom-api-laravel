<?php

namespace App\Api\V1\Requests\Schedule\ScheduleLayer;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpsertRequest extends FormRequest
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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $this->merge([
            // make sure we have a project id
            'project_id' => $this->project_id ?? $authUser?->userProject?->project_id,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // only set if we're updating
        /** @var \App\Models\ChannelLayer $channelLayer */
        $channelLayer = $this->route('channelLayer');

        $rules = [
            'schedule_set_id' => [
                'required',
                'integer',
                // Channel belongs to this active company
                Rule::exists('schedule_sets', 'id')->where(function ($query) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('project_id', $this->project_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'name'            => [
                'required',
                'string',
                'max:255',
                // Layer name is unique to this channel
                // todo: implement a UniqueWithinChannel rule
                Rule::unique('channel_layers', 'name')
                    ->where(function($query) use ($channelLayer) {
                        return $query->where('schedule_set_id', $this->input('schedule_set_id', $channelLayer?->schedule_set_id))
                                     ->whereNull('deleted_at');
                    }),
            ],
            'description'     => 'nullable|string',
            'sort_order'      => 'nullable|integer|min:1',
        ];

        // update-specific rules
        if ($this->method() == 'PUT') {
            $rules['id'] = ['required', 'integer', 'exists:channel_layers,id'];
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'schedule_set_id.exists' => 'The Schedule Set must exist and belong to this project.',
        ];
    }
}
