<?php

namespace App\Api\V1\Requests\Page;

use App\Enums\ChannelEntityType;
use App\Traits\Requests\DingoFormRequestAdapter;
use App\Traits\Rules\WorkflowRunLoggable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateRequest extends FormRequest
{
    use DingoFormRequestAdapter, WorkflowRunLoggable;

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
        /** @var \App\Models\Page $page */
        $page = $this->route('page');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $pageGroupIdRules = [];

        $hasPlaylist = $this->route()->hasParameter('playlist');
        /** @var \App\Models\Playlist|null $playlist */
        $playlist = $hasPlaylist ? $this->route('playlist') : null;

        // If the parameter "playlist" does not exist, then it is a company page request.
        if ($hasPlaylist) {
            $pageGroupIdRules['page_group_id'] = [
                'nullable',
                'integer',
                Rule::exists('page_groups', 'id')->where(function ($query) use ($authUser, $playlist) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('playlist_id', $playlist->id)
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ];
        }

        $channelEntityTable = ChannelEntityType::getTableFrom($this->input('channel_entity_type'));

        return [
            ...$pageGroupIdRules,
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('templates', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        //->where('company_id', $authUser->company_id)
                        ->where(function($q) use ($authUser) {
                              $q->where('company_id', $authUser->company_id)
                                ->orWhere('preset', 'd3');
                        })
                        ->whereNull('deleted_at');
                }),
            ],
            'channel_id' => [
                'nullable',
                'integer',
                Rule::exists('channels', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'channel_entity_type' => [
                'nullable',
                Rule::when($this->input('channel_entity_id'), [
                    'required',
                ]),
                new Enum(ChannelEntityType::class),
            ],
            'channel_entity_id' => [
                'nullable',
                'integer',
                Rule::when(! is_null($channelEntityTable), [
                    Rule::exists($channelEntityTable, 'id')->where(function ($query) use ($authUser) {
                        /** @var \Illuminate\Database\Query\Builder $query */
                        return $query
                            ->where('company_id', $authUser->company_id)
                            ->whereNull('deleted_at');
                    }),
                ]),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('pages')->where(function ($query) use ($authUser, $playlist, $page) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    $pageQuery = $playlist?->pages() ?? $authUser->company->withoutPlaylistPages();
                    $pageGroupId = null;

                    if ($this->has('page_group_id')) {
                        $pageGroupId = $this->input('page_group_id');
                        $pageGroupId = is_scalar($pageGroupId) ? (int) $pageGroupId : null;
                    } else {
                        $page->setParentModel($playlist);
                        $pageGroupId = $page->page_group_id;
                    }

                    $pageQuery->wherePivot('group_id', $pageGroupId);
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                        ->whereIn('id', $pageQuery->select('pages.id'));
                })->ignore($page),
            ],
            'description' => 'nullable|string',
            'is_live' => 'integer',
            'page_number' => 'sometimes|integer|min:0',
            'preview_img' => 'sometimes',
            'color' => 'nullable|string',
            'data' => 'nullable|array',
            'edited_fields' => 'nullable|string',
            'subchannel' => 'nullable|string',
            'workflow_run_log_id' => $this->getWorkflowRunLogIdRules($authUser),
            // could be a formatted string or a number of seconds
            'duration' => Rule::when($this->input('duration') !== null, function(){
                if(str_contains($this->input('duration'), ':')){
                    return ['date_format:H:i:s'];
                }
                return ['numeric', 'min:0', 'max:86399'];
            }),
        ];
    }
}
