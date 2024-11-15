<?php

namespace App\Api\V1\Requests;

use App\Rules\NodeDepth;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

abstract class BatchDuplicateGroupRequest extends FormRequest
{
    use DingoFormRequestAdapter;

    abstract protected function getItemClass(): string;

    abstract protected function getGroupClass(): string;

    protected function addParentScopeRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        return $query;
    }

    protected function addGlobalScopeRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        return $query;
    }

    protected function addItemRuleDatabaseConditions(
        EloquentBuilder|Builder|Exists|Unique $query
    ): EloquentBuilder|Builder|Exists|Unique
    {
        return $this->addGlobalScopeRuleDatabaseConditions($query);
    }

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

        $itemTable = (new ($this->getItemClass()))->getTable();

        $groupClass = $this->getGroupClass();
        $groupTable = (new $groupClass)->getTable();

        return [
            'ids' => 'array',
            'ids.*' => [
                'integer',
                $this->addGlobalScopeRuleDatabaseConditions(
                    Rule::exists($groupTable, 'id')
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                $this->addParentScopeRuleDatabaseConditions(
                    Rule::exists($groupTable, 'id')
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                ),
                (new NodeDepth($groupClass))->setChildrenIdFieldName('ids'),
            ],
            'item_ids' => 'array',
            'item_ids.*' => [
                'integer',
                $this->addItemRuleDatabaseConditions(
                    Rule::exists($itemTable, 'id')
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'sort_order' => 'nullable|integer|min:1',
        ];
    }
}
