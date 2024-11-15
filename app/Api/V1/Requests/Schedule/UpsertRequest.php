<?php

namespace App\Api\V1\Requests\Schedule;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // print_r($this->input('rules'));
        // die;

        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $rules = [
            'schedule_listing_id'                            => [
                'required',
                'integer',
                'exists:schedule_listings,id'
            ],
            'scheduleable_id'                          => [
                'nullable',
                'integer',
            ],
            // TODO: use class_implements(ScheduleableInterface::class) instead of hardcode array
            'scheduleable_type'                          => [
                'nullable',
                'string',
            ],
            'timezone'                                   => ['string', 'nullable'],
            'summary'                                    => ['string', 'nullable'],
            'ranges'                                     => ['string', 'nullable'],
        ];

        if($this->method() == 'PUT'){
            // update specific rules
            $rules['id']= ['required', 'integer', 'exists:schedules,id'];
        }

        /*// check morph relation
        // TODO: refactor and move to dedicated Rule class
        if (Str::contains('page', strtolower($this->input('scheduleable_type')))) {
            $rules['scheduleable_id'][] = Rule::exists('pages', 'id')->where(function ($query) use ($authUser) {
                /** @var \Illuminate\Database\Query\Builder $query *
                return $query
                    // ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at');
            });
        } elseif (Str::contains('playlist', strtolower($this->input('scheduleable_type')))) {
            $rules['scheduleable_id'][] = Rule::exists('playlists', 'id')->where(function ($query) use ($authUser) {
                /** @var \Illuminate\Database\Query\Builder $query *
                return $query
                    // ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at');
            });
        }*/

        return $rules;
    }
}
