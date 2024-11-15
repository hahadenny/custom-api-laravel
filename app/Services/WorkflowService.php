<?php

namespace App\Services;

use App\Contracts\Models\TreeSortable;
use App\Models\Page;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Workflow\StepRequest;
use App\Services\Workflow\Tree\Node;
use App\Services\Workflow\Tree\NodeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function listing(User $authUser): LengthAwarePaginator
    {
        $query = $authUser->company->workflows()->orderBy('name')->orderBy('id');
        return $query->paginate($query->count());
    }

    public function store(User $authUser, array $params = []): Workflow
    {
        $workflow = new Workflow($params);
        $workflow->company()->associate($authUser->company);
        $workflow->user()->associate($authUser);
        $workflow->save();

        return $workflow;
    }

    public function run(Workflow $workflow, User $authUser, array $params = []): void
    {
        DB::transaction(function () use ($workflow, $authUser, $params) {
            $this->runWorkflow($workflow, $authUser, $params);
        });
    }

    public function runFilterSteps(User $authUser, array $params = []): SupportCollection
    {
        $dataTree = $this->buildDataTree($params['steps']);

        $stepParams = $dataTree;
        $stepParams['global'] = [
            'user' => $authUser,
            'params' => $params,
        ];

        $workflow = new Workflow(['type' => $params['type']]);
        $workflow->company()->associate($authUser->company);

        $tree = $this->buildTreeFromArray($dataTree, $workflow);

        if (is_null($tree)) {
            return collect();
        }

        return $tree
            ->handle(new StepRequest($stepParams))
            ->getParam('models')
            ->map(function (Model $model) {
                $item = $model->only(['id', 'name']);
                if ($model instanceof Page) {
                    $item['playlist_ids'] = $model->playlists->pluck('id');
                }
                return $item;
            })
            ->sortBy(['name', 'id']);
    }

    public function workflowRunLogStore(Workflow $workflow, User $authUser): array
    {
        /** @var \App\Services\WorkflowRunLogService $workflowRunLogService */
        $workflowRunLogService = app(WorkflowRunLogService::class);
        return $workflowRunLogService->createByWorkflow($workflow, $authUser)->only(['id']);
    }

    public function revert(Workflow $workflow, User $authUser): void
    {
        DB::transaction(function () use ($workflow, $authUser) {
            $this->revertWorkflow($workflow, $authUser);
        });
    }

    protected function runWorkflow(Workflow $workflow, User $authUser, array $params): void
    {
        $dataTree = $this->buildDataTree($workflow->data);

        $stepParams = $dataTree;
        $stepParams['global'] = [
            'user' => $authUser,
            'params' => $params,
        ];

        $logs = [];
        $isListenerActive = true;

        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *', 'eloquent.restored: *'],
            function ($event, $models) use (&$logs, &$isListenerActive) {
                if (! $isListenerActive) {
                    return;
                }

                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = $models[0];

                $logs[] = [
                    'event' => explode(':', $event)[0],
                    'model_id' => $model->id,
                    'model_class' => get_class($model),
                    'new' => $model->getChanges(),
                    'old' => collect($model->getOriginal())->only(array_keys($model->getChanges()))->toArray(),
                ];
            }
        );

        $this->buildTreeFromArray($dataTree, $workflow)->handle(new StepRequest($stepParams));
        $isListenerActive = false;

        /** @var \App\Services\WorkflowRunLogService $workflowRunLogService */
        $workflowRunLogService = app(WorkflowRunLogService::class);
        $workflowRunLogService->createByWorkflow($workflow, $authUser, $logs);

        $logs = [];
    }

    protected function revertWorkflow(Workflow $workflow, User $authUser): void
    {
        if (is_null($workflow->latestWorkflowRunLog)) {
            throw ValidationException::withMessages(['The Workflow Run Log were not found.']);
        }
        if ($workflow->latestWorkflowRunLog->runByUser->id !== $authUser->id) {
            throw ValidationException::withMessages(['Another user run this workflow.']);
        }

        $listingPivots = [];

        foreach (array_reverse($workflow->latestWorkflowRunLog->data) as $log) {
            /** @var \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\SoftDeletes $model */
            $model = null;

            if (is_a($log['model_class'], TreeSortable::class, true)) {
                $model = $listingPivots[$log['model_id']] ?? null;
            }

            if (is_null($model)) {
                $model = $log['model_class']::query()->withTrashed()->find($log['model_id']);
            }

            foreach ($log['old'] as $name => $value) {
                if ($name !== 'updated_at' && $name !== 'deleted_at') {
                    $model->{$name} = $value;
                }
            }

            if ($log['event'] === 'eloquent.created' || $log['event'] === 'eloquent.restored') {
                $model->delete();
            } elseif ($log['event'] === 'eloquent.deleted') {
                $model->restore();
            } else {
                $model->save();
            }

            if ($model instanceof TreeSortable) {
                if ($model->trashed()) {
                    $model->updateSortOrderOfList();
                    unset($listingPivots[$model->id]);
                } else {
                    $listingPivots[$model->id] = $model;
                }
            }
        }

        foreach ($listingPivots as $listingPivot) {
            $listingPivot->moveToCurrentOrder();
        }

        $workflow->latestWorkflowRunLog->delete();
    }

    protected function buildTreeFromArray(array $data, Workflow $workflow): ?NodeInterface
    {
        return $this->buildTreeFromArrayRecursive($data, $workflow);
    }

    protected function buildTreeFromArrayRecursive(array $data, Workflow $workflow): ?NodeInterface
    {
        if (empty($data)) {
            return null;
        }

        /** @var \App\Services\Workflow\Steps\Step $step */
        $step = new (Workflow::stepClass($data['type']));
        $step->setWorkflow($workflow);

        $node = new Node($step);

        foreach ($data['steps'] ?? [] as $dataStep) {
            $node->addChild($this->buildTreeFromArrayRecursive($dataStep, $workflow));
        }

        return $node;
    }

    protected function buildDataTree(array $data): array
    {
        $modifiedData = [];

        foreach ($data as $item) {
            $modifiedData[] = $item;

            $saveAfterSteps = [
                'set_data_step',
                'spreadsheet_import',
                'google_sheet_import',
            ];

            if (isset($item['type']) && in_array($item['type'], $saveAfterSteps, true)) {
                $modifiedData[] = ['type' => 'save_step'];
            }
        }

        $data = $modifiedData;

        $tree = null;

        foreach (array_reverse($data) as $step) {
            if (is_null($step)) {
                throw ValidationException::withMessages(['The Step must not be null.']);
            }

            if ($step['type'] === 'start' || $step['type'] === 'end') {
                continue;
            }

            $stepData = $step['data'] ?? [];

            $convertedStep = [
                'type' => $step['type'],
            ];

            if (array_key_exists('rules', $stepData)) {
                $convertedStep['rules'] = $stepData['rules'];
            }
            if (array_key_exists('data', $stepData)) {
                $convertedStep['data'] = $stepData['data'];
            }

            if (! is_null($tree)) {
                $convertedStep['steps'] = [$tree];
            }

            $tree = $convertedStep;
        }

        return $tree ?? [];
    }
}
