<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Workflow\RunFilterStepsRequest;
use App\Api\V1\Requests\Workflow\RunRequest;
use App\Api\V1\Requests\Workflow\StoreRequest;
use App\Api\V1\Requests\Workflow\UpdateRequest;
use App\Api\V1\Resources\WorkflowResource;
use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class WorkflowController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Workflow::class, 'workflow');
    }

    /**
     * Display a listing of workflows.
     *
     * @group Workflow
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(WorkflowService $workflowService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return WorkflowResource::collection($workflowService->listing($authUser));
    }

    /**
     * Store a newly created workflow.
     *
     * @group Workflow
     *
     * @param  \App\Api\V1\Requests\Workflow\StoreRequest  $request
     * @param  \App\Services\WorkflowService  $workflowService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, WorkflowService $workflowService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new WorkflowResource($workflowService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified workflow.
     *
     * @group Workflow
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \App\Api\V1\Resources\WorkflowResource
     */
    public function show(Workflow $workflow)
    {
        return new WorkflowResource($workflow);
    }

    /**
     * Update the specified workflow.
     *
     * @group Workflow
     *
     * @param  \App\Api\V1\Requests\Workflow\UpdateRequest  $request
     * @param  \App\Models\Workflow  $workflow
     * @return \App\Api\V1\Resources\WorkflowResource
     */
    public function update(UpdateRequest $request, Workflow $workflow)
    {
        return new WorkflowResource(tap($workflow)->update($request->validated()));
    }

    /**
     * Run the specified workflow.
     *
     * @group Workflow
     *
     * @param  \App\Api\V1\Requests\Workflow\RunRequest  $request
     * @param  \App\Models\Workflow  $workflow
     * @param  \App\Services\WorkflowService  $workflowService
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function run(RunRequest $request, Workflow $workflow, WorkflowService $workflowService)
    {
        $this->authorize('run', $workflow);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $workflowService->run($workflow, $authUser, $request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Run the filter steps.
     *
     * @group Workflow
     *
     * @param  \App\Api\V1\Requests\Workflow\RunFilterStepsRequest  $request
     * @param  \App\Services\WorkflowService  $workflowService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function runFilterSteps(RunFilterStepsRequest $request, WorkflowService $workflowService)
    {
        $this->authorize('runFilterSteps', Workflow::class);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return JsonResource::collection($workflowService->runFilterSteps($authUser, $request->validated()));
    }

    /**
     * Store a newly created workflow run log.
     *
     * @group Workflow
     *
     * @param  \App\Models\Workflow  $workflow
     * @param  \App\Services\WorkflowService  $workflowService
     * @return \Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function workflowRunLogStore(Workflow $workflow, WorkflowService $workflowService)
    {
        $this->authorize('workflowRunLogStore', $workflow);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return JsonResource::make($workflowService->workflowRunLogStore($workflow, $authUser));
    }

    /**
     * Revert the specified workflow.
     *
     * @group Workflow
     *
     * @param  \App\Models\Workflow  $workflow
     * @param  \App\Services\WorkflowService  $workflowService
     * @return void
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function revert(Workflow $workflow, WorkflowService $workflowService)
    {
        $this->authorize('revert', $workflow);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $workflowService->revert($workflow, $authUser);
    }

    /**
     * Remove the specified workflow.
     *
     * @group Workflow
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Workflow $workflow)
    {
        $workflow->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
