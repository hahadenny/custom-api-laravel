<?php

namespace App\Api\V1\Requests\Page;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BatchAttachRequest extends FormRequest
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
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'ids' => 'required|array',
            'ids.*' => [
                'integer',
                Rule::exists('pages', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereIn('id', $authUser->company->withoutPlaylistPages()->select(['pages.id']))
                        ->whereNull('original_id')
                        ->whereNotIn(
                            'id',
                            $authUser->company->pages()->whereNotNull('original_id')->select(['original_id'])
                        )
                        ->whereNull('deleted_at');
                }),
            ],
            'playlist_id' => [
                'required',
                'integer',
                Rule::exists('playlists', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'page_group_id' => [
                'nullable',
                'integer',
                Rule::exists('page_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */

                    $playlistId = $this->input('playlist_id');
                    $playlistId = is_scalar($playlistId) ? (int) $playlistId : null;

                    return $query
                        ->where('playlist_id', $playlistId)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'sort_order' => 'nullable|integer|min:1',
        ];
    }
}
