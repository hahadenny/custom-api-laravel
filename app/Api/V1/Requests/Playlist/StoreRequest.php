<?php

namespace App\Api\V1\Requests\Playlist;

use App\Enums\PlaylistType;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('playlists')->where(function ($query) use ($authUser, $project) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('playlist_group_id', $this->input('playlist_group_id'))
                        ->where('project_id', $project->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'type' => [
                new Enum(PlaylistType::class),
            ],
            'playlist_group_id' => [
                'nullable',
                'integer',
                Rule::exists('playlist_groups', 'id')->where(function ($query) use ($authUser, $project) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('project_id', $project->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:1',
        ];
    }
}
