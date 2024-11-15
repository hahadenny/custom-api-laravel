<?php

namespace App\Api\V1\Requests\Schedule\ScheduleRule;

use App\Enums\Schedule\Frequency;
use App\Services\Schedule\Helpers\ScheduleRuleFormParser;
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
            'schedule_id'         => [
                'required',
                'integer',
                'exists:schedules,id',
            ],
            'schedule_listing_id' => [
                'required',
                'integer',
                'exists:schedule_listings,id',
            ],
            'is_exclusion'        => ['boolean', 'nullable'],
            // BYMINUTE
            'run_on_minutes'      => ['array', 'nullable'],
            // BYHOUR
            'run_on_hours'        => ['array', 'nullable'],
            // BYMONTH
            'run_on_months'       => ['array', 'nullable'],
            // BYDAY
            'run_on_days'         => [
                'array',
                // normalize form values' case to match enum
                // also allow empty value
                // Rule::in([...array_map(function($val){ return strtolower(ucfirst($val)); }, Day::values()), '', null, ' ']),
                'nullable',
            ],
            // DTSTART
            'start_date'          => ['date', 'nullable'],
            'start_time'          => ['string', 'nullable'],
            // end of "event window" -> for calculating "event" duration
            'end_date'            => ['date', 'nullable'],
            'end_time'            => ['string', 'nullable'],
            // FREQ
            // all frequencies, including "CUSTOM"
            'freq'                => [
                'string',
                Rule::in([...Frequency::names(), '', ' ', null]), // Rule::in(array_keys(RRule::FREQUENCIES)),
                'nullable',
            ],
            // INTERVAL
            'interval'            => ['integer', 'nullable'],
            // COUNT
            'max_occurrences'     => [
                'integer',
                // Rule::prohibitedIf(!empty($this->input('end_date'))), // Laravel 9 only
                'nullable',
            ],
            // MONTHDAYS
            'month_days'          => [
                // todo: make sure days exist in the relevant month if it's numeric
                'nullable',
            ],
            'ends'                => ['required', 'string'],
            'repeat_end_date'     => ['date', 'nullable'],
            'repeat_end_time'     => ['string', 'nullable'],
            'custom_freq'         => ['string', 'nullable'],
            'all_day'             => ['boolean', 'nullable'],
        ];

        if($this->method() == 'PUT') {
            // update-specific rules
            $rules['id'] = ['required', 'integer', 'exists:schedule_rules,id'];
        }

        /*// TODO: Rules cannot have both max occurrences and end dates
        // -- this cant really be done due to radio buttons anyway
        if ( !empty($this->input('end_date')) && !empty($this->input('max_occurrences'))) {
            $rules['end_date'][] = 'after_or_equal:start_date';
        }
        /*if( !empty($this->input('max_occurrences')) && !empty($this->input('end_date'))){
            $rules['max_occurrences'][]= 'prohibited';
        }*/

        // make sure end date >= start date if start date is set
        if( !empty($this->input('start_date'))) {
            $rules['end_date'][] = 'after_or_equal:start_date';
            $rules['repeat_end_date'][] = 'after_or_equal:start_date';
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
            'max_occurrences.prohibited' => 'A schedule rule cannot have both an end date and a maximum number of occurrences specified.',
        ];
    }

    /**
     * Handle validated values.
     *
     * Done here instead of passedValidation() because we want the values to eventually
     * be returned by $request->validated() for mass-assignment
     *
     * @return array|int[]|mixed
     * @throws \Exception
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        $parser = new ScheduleRuleFormParser();

        return $parser->parse($data, ($this->route('schedule') ?? null));
    }
}
