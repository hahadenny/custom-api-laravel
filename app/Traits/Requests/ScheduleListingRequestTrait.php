<?php

namespace App\Traits\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ScheduleListingRequestTrait
{
    abstract protected function additionalRules() : array;

    protected function additionalMessages() : array
    {
        return [];
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
        return array_merge([
            'parent_id'       => [
                Rule::requiredIf(
                    // layer has no parent
                    (!Str::contains(strtolower($this->input('scheduleable_type')), 'layer')
                        // we can omit parent_id when just changing duration
                        && is_null($this->input('duration')))
                    // parent_id required unless we're deleting
                    && $this->method() !== 'DELETE'
                ),
                'integer',
                Rule::exists('schedule_listings', 'id')
                    ->whereNull('deleted_at'),
            ],
            'duration'        => [
                'nullable',
                'string',
            ],
            // sort_order is only updated via ListingObserver
        ], $this->additionalRules());
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return array_merge([
            'parent_id.exists'       => 'The item must have a parent.',
        ], $this->additionalMessages());
    }
}
