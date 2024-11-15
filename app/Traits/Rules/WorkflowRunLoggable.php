<?php

namespace App\Traits\Rules;

use App\Models\User;
use App\Models\WorkflowRunLog;
use Illuminate\Validation\Rule;

trait WorkflowRunLoggable
{
    protected function getWorkflowRunLogIdRules(User $authUser): array
    {
        return [
            'integer',
            Rule::exists('workflow_run_logs', 'id')->where(function ($query) use ($authUser) {
                /** @var \Illuminate\Database\Query\Builder $query */
                $query
                    ->where('run_by_user_id', $authUser->id)
                    ->whereIn('id', WorkflowRunLog::query()
                        ->selectRaw(('MAX(id)'))
                        ->where('run_by_user_id', $authUser->id)
                    )
                    ->whereNull('deleted_at');
            }),
        ];
    }
}
