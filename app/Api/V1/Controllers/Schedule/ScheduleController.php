<?php

namespace App\Api\V1\Controllers\Schedule;

use App\Api\V1\Requests\Schedule\UpsertRequest;
use App\Api\V1\Resources\Schedule\ScheduleResource;
use App\Models\User;
use App\Services\Schedule\ScheduleService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Schedule
 */
class ScheduleController extends \App\Http\Controllers\Controller
{
    public function __construct(protected ScheduleService $service)
    {
        //# authorizeResource(resource model name to authorize, route param name for the model instance)
        //# controller methods will be mapped to their corresponding policy method
        //# e.g., show --> view, edit --> update
        //# https://laravel.com/docs/8.x/authorization#authorizing-resource-controllers
        // $this->authorizeResource(Schedule::class, 'schedule');
    }

    /**
     * Store a newly created resource in storage.
     *
     * On validation failure in StoreRequest, an HTTP response with
     * a 422 status code will be returned to the user, including
     * a JSON representation of the validation errors
     *
     * @link https://laravel.com/docs/9.x/validation#validation-error-response-format
     *
     * @param \App\Api\V1\Requests\Schedule\UpsertRequest           $request
     * @param \App\Services\Schedule\Helpers\ScheduleRulesetAdapter $rulesetAdapter
     *
     * @return \Illuminate\Http\JsonResponse|object
     * @throws \Exception
     */
    public function store(UpsertRequest $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        // FOR POSTMAN TESTING -- TODO: REMOVE
        if(config('app.env') === 'local'){
            $authUser ??= User::find(1);
        }
        // -- END TESTING

        return (new ScheduleResource($this->service->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
