<?php

namespace App\Api\V1\Requests\Project;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'filled',
                'string',
                'max:255',
                Rule::unique('projects')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                })->ignore($project),
            ],
            'workspace_id' => [
                'nullable',
                'integer',
                Rule::exists('workspaces', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('user_id', $authUser->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'sort_order' => 'nullable|integer|min:1',
            'workspace_layout' => 'nullable|array',
            'user_project_state' => 'nullable|array',
            'user_project_is_active' => 'nullable|boolean',
        ];
    }
}
