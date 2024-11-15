<?php

namespace App\Api\V1\Requests\Schedule\ScheduleSet;

use App\Models\Schedule\States\PlayoutState;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\ModelStates\Validation\ValidStateRule;

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
        $activeUserProjectPivot = $authUser->userProject;

        $this->merge([
            // make sure we have a project id
            'project_id' => $this->project_id ?? $activeUserProjectPivot->project_id,
            'description' => $this->description ?? '',
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /** @var \App\Models\Schedule\ScheduleSet $scheduleSet */
        $scheduleSet = $this->route('schedule_set');

        $rules = [
            'name'        => [
                'required',
                'string',
                'max:255',
                Rule::unique('schedule_sets')->where(function ($query) use ($scheduleSet) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        // ->where('schedule_set_group_id', $this->input('schedule_set_group_id', $scheduleSet->schedule_set_group_id))
                        ->where('project_id', $this->project_id);
                })->ignore($scheduleSet),
            ],
            'project_id'  => [
                'required',
                'integer',
                'exists:projects,id',
            ],
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:1',
            'status'      => ValidStateRule::make(PlayoutState::class)->nullable(),
        ];

        return $rules;
    }
}
