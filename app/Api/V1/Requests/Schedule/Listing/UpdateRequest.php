<?php

namespace App\Api\V1\Requests\Schedule\Listing;

use App\Traits\Requests\DingoFormRequestAdapter;
use App\Traits\Requests\ScheduleListingRequestTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    use DingoFormRequestAdapter, ScheduleListingRequestTrait;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    protected function additionalRules() : array
    {
        return [
            'id' => [
                'required',
                'integer',
                Rule::exists('schedule_listings', 'id')
                    ->whereNull('deleted_at'),
            ],
        ];
    }

}
