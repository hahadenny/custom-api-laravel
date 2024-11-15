<?php

use App\Api\V1\Controllers\Schedule\ProjectScheduleSetController;
use App\Api\V1\Controllers\Schedule\ScheduleController;
use App\Api\V1\Controllers\Schedule\ScheduleLayerPlaylistController;
use App\Api\V1\Controllers\Schedule\ScheduleListingController;
use App\Api\V1\Controllers\Schedule\ScheduleRuleController;
use App\Api\V1\Controllers\Schedule\ScheduleSetLayerController;
use App\Api\V1\Controllers\Schedule\ScheduleSetScheduleListingController;
use App\Api\V1\Controllers\Schedule\UserScheduleSetController;
use Dingo\Api\Routing\Router;

/** @var Router $api */

// Schedule Listing Pivots
$api->match(['put', 'patch'],'schedule-listings/batch', [ScheduleListingController::class, 'batchUpdate']);
$api->match(['delete'],'schedule-listings/batch', [ScheduleListingController::class, 'batchDestroy']);
$api->resource('schedule-listings', ScheduleListingController::class, ['only' => 'update']);

// Playlist of Layer
$api->match(['post'], 'schedule-layers/{schedule_layer}/playlists', [ScheduleLayerPlaylistController::class, 'store']);

// Schedules
$api->resource('schedules', ScheduleController::class, ['only' => 'store']);

// Schedule Rules
$api->resource('schedules.rules', ScheduleRuleController::class, ['except' => ['show']]);

// Schedule Listing for ScheduleSet
$api->match(['get'],'schedule-sets/{schedule_set}/calendar', [ScheduleSetScheduleListingController::class, 'listingForCalendar']);
$api->resource('schedule-sets.schedule-listings', ScheduleSetScheduleListingController::class, ['only' => 'index']);
// Schedule Sets for Scheduler table grid dropdown, filtered by project
$api->resource('projects.schedule-sets', ProjectScheduleSetController::class, ['only' => ['index', 'update', 'store']]);
$api->resource('schedule-sets.layers', ScheduleSetLayerController::class, ['only' => ['store'], 'layers' => 'channelLayer']);
$api->resource('users.schedule-sets', UserScheduleSetController::class, ['only' => ['update']]);
