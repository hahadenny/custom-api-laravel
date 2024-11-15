<?php

namespace App\Api\V1\Requests\TemplateGroup;

use App\Models\Template;
use App\Models\TemplateGroup;
use App\Rules\NodeDepth;
use App\Rules\NotInArray;
use App\Rules\UniqueNameByIds;
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
        /** @var \App\Models\TemplateGroup $group */
        $group = $this->route('template_group');
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('template_groups')->where(function ($query) use ($authUser, $group) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('parent_id', $this->input('parent_id', $group->parent_id))
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                })->ignore($group),
            ],
            'sort_order' => 'nullable|integer|min:1',
            'children_ids' => [
                'array',
                UniqueNameByIds::make(TemplateGroup::class)->where(function ($query) use (
                    $authUser, $group
                ) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    return $query
                        ->where('parent_id', $group->id)
                        ->where('company_id', $authUser->company_id);
                }),
            ],
            'children_ids.*' => [
                'integer',
                'not_in:'.$group->id,
                Rule::exists('template_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:'.$group->id,
                new NotInArray('children_ids.*'),
                Rule::exists('template_groups', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
                (new NodeDepth(TemplateGroup::class))
                    ->setMiddle($group)
                    ->setChildrenIdFieldName('children_ids'),
            ],
            'item_ids' => [
                'array',
                UniqueNameByIds::make(Template::class)->where(function ($query) use (
                    $authUser, $group
                ) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    return $query
                        ->where('template_group_id', $group->id)
                        ->where('company_id', $authUser->company_id);
                }),
            ],
            'item_ids.*' => [
                'integer',
                Rule::exists('templates', 'id')->where(function ($query) use ($authUser) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }
}
