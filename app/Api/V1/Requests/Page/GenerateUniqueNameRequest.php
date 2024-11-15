<?php

namespace App\Api\V1\Requests\Page;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GenerateUniqueNameRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        /** @var \App\Models\Playlist $playlist */
        $playlist = $this->route('playlist');

        return [
            'name' => 'required|string|max:255',
            'page_group_id' => [
                'nullable',
                'integer',
                Rule::exists('page_groups', 'id')->where(function ($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }
}
