<?php

namespace App\Api\V1\Requests\Playlist;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BatchExportRequest extends FormRequest
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
            'ids' => 'array',
            'ids.*' => [
                'integer',
                Rule::exists('playlists', 'id')
                    ->where('project_id', $project->id)
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at'),
            ],
            'group_ids' => 'array',
            'group_ids.*' => [
                'integer',
                Rule::exists('playlist_groups', 'id')
                    ->where('project_id', $project->id)
                    ->where('company_id', $authUser->company_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
