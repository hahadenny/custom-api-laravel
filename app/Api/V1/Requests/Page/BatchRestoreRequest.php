<?php

namespace App\Api\V1\Requests\Page;

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
                Rule::exists('pages', 'id')
                    ->where('company_id', $authUser->company_id)
                    ->whereNotNull('deleted_at')
                    ->where(function ($q) use ($playlist) {
                        /** @var \Illuminate\Database\Query\Builder $q */
                        return $q->whereIn('id', $playlist->pagesWithPivotTrashed()->onlyTrashed()->select(['pages.id']));
                    }),
            ],
        ];
    }
}
