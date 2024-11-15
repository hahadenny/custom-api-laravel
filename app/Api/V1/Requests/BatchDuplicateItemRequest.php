<?php

namespace App\Api\V1\Requests;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

abstract class BatchDuplicateItemRequest extends FormRequest
{
    use DingoFormRequestAdapter;

    abstract protected function getItemClass(): string;

    abstract protected function getGroupClass(): string;

    abstract protected function getGroupIdField(): string;

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
        $groupTable = (new ($this->getGroupClass()))->getTable();

        return [
            'ids' => 'required|array',
            'ids.*' => [
                'integer',
                $this->addGlobalScopeRuleDatabaseConditions(
                    Rule::exists($itemTable, 'id')
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            $this->getGroupIdField() => [
                'nullable',
                'integer',
                $this->addParentScopeRuleDatabaseConditions(
                    Rule::exists($groupTable, 'id')
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'sort_order' => 'nullable|integer|min:1',
            'new_page_num' => 'nullable|integer|min:1',
            'move_to' => 'nullable|boolean'
        ];
    }
}
