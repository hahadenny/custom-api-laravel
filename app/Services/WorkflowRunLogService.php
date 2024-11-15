<?php

namespace App\Services;

use App\Enums\WorkflowType;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\PlaylistListing;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRunLog;
use Exception;
use Illuminate\Support\Facades\Event;

class WorkflowRunLogService
{
    protected static ?WorkflowRunLog $listenerWorkflowRunLog = null;
    protected static bool $isListenerRegistered = false;

    public function createByWorkflow(Workflow $workflow, User $authUser, $logs = []): WorkflowRunLog
    {
        /** @var \App\Models\WorkflowRunLog $workflowRunLog */
        $workflowRunLog = $workflow->workflowRunLogs()->make();
        $workflowRunLog->runByUser()->associate($authUser);
        $workflowRunLog->workflow_type = $workflow->type;
        $workflowRunLog->workflow_data = $workflow->data;
        $workflowRunLog->data = $logs;
        $workflowRunLog->save();

        return $workflowRunLog;
    }

    public static function startListening(WorkflowRunLog|int $workflowRunLog): void
    {
        if (! is_null(self::$listenerWorkflowRunLog)) {
            throw new Exception('Cannot start model event listening because a previous one is still running.');
        }

        if (is_int($workflowRunLog)) {
            $workflowRunLog = WorkflowRunLog::query()->find($workflowRunLog);
        }

        self::$listenerWorkflowRunLog = $workflowRunLog;

        if (self::$isListenerRegistered) {
            return;
        }

        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *', 'eloquent.restored: *'],
            function ($event, $models) {
                if (is_null(self::$listenerWorkflowRunLog)) {
                    return;
                }

                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = $models[0];
                $classes = static::getListeningClasses();

                if (! is_null($classes) && ! in_array(get_class($model), $classes, true)) {
                    return;
                }

                $data = self::$listenerWorkflowRunLog->data;

                $data[] = [
                    'event' => explode(':', $event)[0],
                    'model_id' => $model->id,
                    'model_class' => get_class($model),
                    'new' => $model->getChanges(),
                    'old' => collect($model->getOriginal())->only(array_keys($model->getChanges()))->toArray(),
                ];

                self::$listenerWorkflowRunLog->data = $data;
            }
        );

        self::$isListenerRegistered = true;
    }

    public static function stopListening(bool $saveLog = false): void
    {
        $workflowRunLog = self::$listenerWorkflowRunLog;
        self::$listenerWorkflowRunLog = null;

        if ($saveLog && ! is_null($workflowRunLog)) {
            $workflowRunLog->save();
        }
    }

    protected static function getListeningClasses(): ?array
    {
        return match (self::$listenerWorkflowRunLog->workflow_type) {
            WorkflowType::Page => [
                Page::class,
                PageGroup::class,
                PlaylistListing::class,
            ],
        };
    }
}
