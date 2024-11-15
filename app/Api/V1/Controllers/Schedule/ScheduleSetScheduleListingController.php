<?php

namespace App\Api\V1\Controllers\Schedule;

use App\Api\V1\Resources\Schedule\ScheduleListingResource;
use App\Http\Controllers\Controller;
use App\Models\Schedule\ScheduleSet;
use App\Services\Schedule\ScheduleListingService;

/**
 * Methods pertaining to the schedule listing for a given schedule set
 *
 * @group Schedule Listing
 */
class ScheduleSetScheduleListingController extends Controller
{
    public function __construct()
    {
        // $this->middleware(['can:view,scheduleSet']);
        //
        //# authorizeResource(resource model name to authorize, route param name for the model instance)
        //# controller methods will be mapped to their corresponding policy method
        //# e.g., show --> view, edit --> update
        //# https://laravel.com/docs/8.x/authorization#authorizing-resource-controllers
        // $this->authorizeResource(Schedule::class, 'schedule');
        // $this->authorizeResource(ScheduleSet::class, 'scheduleSet');
    }

    /**
     * Display a listing of schedulable entities for a given schedule set
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(ScheduleSet $scheduleSet, ScheduleListingService $service)
    {
        return ScheduleListingResource::collection($service->listing($scheduleSet));
    }

    /**
     * Display a listing of schedulable entities for a given schedule set
     * to display on the calendar view
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function listingForCalendar(ScheduleSet $scheduleSet, ScheduleListingService $service)
    {
        return ScheduleListingResource::collection($service->listingForCalendar($scheduleSet));
    }
}
