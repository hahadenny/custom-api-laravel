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

abstract class BatchUngroupGroupRequest extends FormRequest
{
    use DingoFormRequestAdapter;

    abstract protected function getGroupClass(): string;

    protected function addGroupRuleDatabaseConditions(
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

        $groupTable = (new ($this->getGroupClass()))->getTable();

        return [
            'ids' => 'required|array',
            'ids.*' => [
                'integer',
                $this->addGroupRuleDatabaseConditions(
                    Rule::exists($groupTable, 'id')
                        ->where('company_id', $authUser->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
        ];
    }
}