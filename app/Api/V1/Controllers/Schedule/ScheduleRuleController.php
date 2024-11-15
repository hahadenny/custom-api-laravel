<?php

namespace App\Api\V1\Controllers\Schedule;

use App\Api\V1\Requests\Schedule\ScheduleRule\UpsertRequest;
use App\Api\V1\Resources\Schedule\ScheduleRuleResource;
use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleRule;
use App\Services\Schedule\ScheduleRuleService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Schedule Rule
 */
class ScheduleRuleController extends \App\Http\Controllers\Controller
{
    use AuthorizesBatchRequests;

    public function __construct(protected ScheduleRuleService $service)
    {
        //# middleware(['can:action,routeParam'])
        //# https://laravel.com/docs/8.x/authorization#via-middleware
        // $this->middleware(['can:view,schedule']);

        //# authorizeResource(resource model name to authorize, route param name for the model instance)
        //# controller methods will be mapped to their corresponding policy method
        //# e.g., show --> view, edit --> update
        //# https://laravel.com/docs/8.x/authorization#authorizing-resource-controllers
        // $this->authorizeResource(ScheduleRule::class, 'rule');
    }

    /**
     * Show all rules for a given schedule
     * Use ?exclusions=1 to include exclusion rules
     *
     * @param Request  $request
     * @param Schedule $schedule
     *
     * @return AnonymousResourceCollection
     * @throws \Exception
     */
    public function index(Request $request, Schedule $schedule)
    {
        if($request->input('exclusions') == 1){
            // include exclusions
            return ScheduleRuleResource::collection($schedule->getAllConvertedRules());
        }

        return ScheduleRuleResource::collection($schedule->getConvertedRules());
    }

    /**
     * Store a newly created rule
     *
     * @return JsonResponse|object
     * @throws \Exception
     */
    public function store(UpsertRequest $request, Schedule $schedule)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new ScheduleRuleResource($this->service->store($authUser, $request->validated(), $schedule)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update the specified rule
     *
     * @return JsonResponse|object
     * @throws \Exception
     */
    public function update(UpsertRequest $request, Schedule $schedule, ScheduleRule $rule)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new ScheduleRuleResource($this->service->update($authUser, $request->validated(), $schedule, $rule)))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Remove the specified rule
     *
     * @param Schedule            $schedule
     * @param ScheduleRule        $rule
     * @param ScheduleRuleService $ruleService
     *
     * @return JsonResponse
     */
    public function destroy(Schedule $schedule, ScheduleRule $rule, ScheduleRuleService $ruleService)
    {
        $ruleService->delete($rule, $schedule);
        return response()->json(null, \Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT);
    }
}
