<?php

namespace App\Api\V1\Requests\PageGroup;

use App\Models\PageGroup;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BatchRestoreRequest extends FormRequest
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
        /** @var \App\Models\Playlist $playlist */
        $playlist = $this->route('playlist');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'ids' => 'required|array',
            'ids.*' => [
                'integer',
                Rule::exists('page_groups', 'id')->where(function ($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNotNull('deleted_at');
                }),
            ],
        ];
    }
}
