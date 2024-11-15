<?php

use App\Api\V1\Controllers\ChannelController;
use App\Api\V1\Controllers\ChannelGroupController;
use App\Api\V1\Controllers\ClusterController;
use App\Api\V1\Controllers\CompanyController;
use App\Api\V1\Controllers\CompanyIntegrationsController;
use App\Api\V1\Controllers\CompanyPreferencesController;
use App\Api\V1\Controllers\D3Controller;
use App\Api\V1\Controllers\FileController;
use App\Api\V1\Controllers\FileMetaController;
use App\Api\V1\Controllers\GoogleDriveXMLController;
use App\Api\V1\Controllers\GoogleSheetController;
use App\Api\V1\Controllers\PageController;
use App\Api\V1\Controllers\PermissionController;
use App\Api\V1\Controllers\PlaylistController;
use App\Api\V1\Controllers\PlaylistMediaController;
use App\Api\V1\Controllers\PlaylistPageController;
use App\Api\V1\Controllers\PlaylistPageGroupController;
use App\Api\V1\Controllers\PortaPluginController;
use App\Api\V1\Controllers\PortaReleaseController;
use App\Api\V1\Controllers\ProjectController;
use App\Api\V1\Controllers\ProjectPlaylistController;
use App\Api\V1\Controllers\ProjectPlaylistGroupController;
use App\Api\V1\Controllers\SocketServerController;
use App\Api\V1\Controllers\SportsSoccerController;
use App\Api\V1\Controllers\TemplateController;
use App\Api\V1\Controllers\TemplateGroupController;
use App\Api\V1\Controllers\UserController;
use App\Api\V1\Controllers\UserGroupController;
use App\Api\V1\Controllers\UserGroupPermissionController;
use App\Api\V1\Controllers\WeatherController;
use App\Api\V1\Controllers\WorkflowController;
use App\Api\V1\Controllers\WorkspaceController;
use Dingo\Api\Routing\Router;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/** @var Router $api */
$api = app(Router::class);

$api->version('v1', ['middleware' => 'bindings'], function (Router $api) {
    $api->get('ping', 'App\\Api\\V1\\Controllers\\PingController@index');
    $api->get('companies/servers', [CompanyController::class, 'servers']);
    $api->get('servers', [SocketServerController::class, 'servers']);
    $api->resource('companies', CompanyController::class, ['only' => ['index']]);

    $api->group(['prefix' => 'auth'], function(Router $api) {
//        $api->post('signup', 'App\\Api\\V1\\Controllers\\Auth\\SignUpController@signUp');
        $api->post('login', 'App\\Api\\V1\\Controllers\\Auth\\LoginController@login');
        $api->group(['middleware' => ['api.auth'], 'providers' => ['api-key']], function(Router $api) {
            $api->post('auth', 'App\\Api\\V1\\Controllers\\Auth\\LoginController@auth');
        });


        $api->post('recovery', 'App\\Api\\V1\\Controllers\\Auth\\ForgotPasswordController@sendResetEmail');
        $api->post('reset', 'App\\Api\\V1\\Controllers\\Auth\\ResetPasswordController@resetPassword')
            ->name('password.reset');

        $api->post('logout', 'App\\Api\\V1\\Controllers\\Auth\\LogoutController@logout');
        $api->post('refresh', 'App\\Api\\V1\\Controllers\\Auth\\RefreshController@refresh');
        $api->get('me', 'App\\Api\\V1\\Controllers\\ProfileController@show');
        $api->put('me', 'App\\Api\\V1\\Controllers\\ProfileController@update');

        $api->group(['middleware' => 'jwt.auth', 'prefix' => 'impersonate'], function(Router $api) {
            $api->post('/{company}', 'App\\Api\\V1\\Controllers\\Auth\\ImpersonateController@create');
            $api->delete('/', 'App\\Api\\V1\\Controllers\\Auth\\ImpersonateController@destroy');
        });
    });

    $api->group(['middleware' => 'jwt.auth', 'providers' => ['jwt']], function(Router $api) {
        $api->get('refresh', [
            'middleware' => 'jwt.refresh',
            function() {
                return response()->json([
                    'message' => 'By accessing this endpoint, you can refresh your access token at each request. Check out this response headers!'
                ]);
            }
        ]);

        $api->match(['put', 'patch'], 'users/batch', [UserController::class, 'batchUpdate']);
        $api->match(['delete'], 'users/batch', [UserController::class, 'batchDestroy']);
        $api->resource('users', UserController::class);
        $api->resource('permissions', PermissionController::class, ['only' => ['index']]);

        $api->match(['put', 'patch'], 'user-groups/batch', [UserGroupController::class, 'batchUpdate']);
        $api->match(['put', 'patch'], 'user-groups/batch-ungroup', [UserGroupController::class, 'batchUngroup']);
        $api->match(['delete'], 'user-groups/batch', [UserGroupController::class, 'batchDestroy']);
        $api->resource('user-groups', UserGroupController::class);
        $api->resource('user-group-permissions', UserGroupPermissionController::class, ['only' => ['index', 'store']]);

        $api->get('clusters', [ClusterController::class, 'index']);

        $api->resource('companies', CompanyController::class, ['except' => ['index']]);
        $api->resource('socket-servers', SocketServerController::class, ['except' => ['update', 'show']]);

        $api->resource('workspaces', WorkspaceController::class);

        $api->match(['post'], 'projects/{project}/duplicate', [ProjectController::class, 'duplicate']);

        $api->match(['put', 'patch'], 'channels/batch', [ChannelController::class, 'batchUpdate']);
        $api->match(['post'], 'channels/batch-duplicate', [ChannelController::class, 'batchDuplicate']);
        $api->match(['delete'], 'channels/batch', [ChannelController::class, 'batchDestroy']);
        $api->post('channels/sync', [ChannelController::class, 'sync']);
        $api->resource('channels', ChannelController::class);

        $api->match(['put', 'patch'], 'channel-groups/batch', [ChannelGroupController::class, 'batchUpdate']);
        $api->match(['post'], 'channel-groups/batch-duplicate', [ChannelGroupController::class, 'batchDuplicate']);
        $api->match(['put', 'patch'], 'channel-groups/batch-ungroup', [ChannelGroupController::class, 'batchUngroup']);
        $api->match(['delete'], 'channel-groups/batch', [ChannelGroupController::class, 'batchDestroy']);
        $api->resource('channel-groups', ChannelGroupController::class);

        $api->match(['put', 'patch'], 'projects/{project}/playlists/batch', [ProjectPlaylistController::class, 'batchUpdate']);
        $api->match(['post'], 'projects/{project}/playlists/batch-duplicate', [ProjectPlaylistController::class, 'batchDuplicate']);
        $api->match(['post'], 'projects/{project}/playlists/batch-export', [ProjectPlaylistController::class, 'batchExport']);
        $api->match(['post'], 'projects/{project}/playlists/import', [ProjectPlaylistController::class, 'import']);

        $api->match(['put', 'patch'], 'projects/{project}/playlist-groups/batch', [ProjectPlaylistGroupController::class, 'batchUpdate']);
        $api->match(['post'], 'projects/{project}/playlist-groups/batch-duplicate', [ProjectPlaylistGroupController::class, 'batchDuplicate']);
        $api->match(['delete'], 'projects/{project}/playlist-groups/batch', [ProjectPlaylistGroupController::class, 'batchDestroy']);
        $api->match(['put', 'patch'], 'projects/{project}/playlist-groups/batch-ungroup', [ProjectPlaylistGroupController::class, 'batchUngroup']);
        $api->resource('projects.playlist-groups', ProjectPlaylistGroupController::class);

        $api->get('templates/fields', [TemplateController::class, 'fields']);
        $api->match(['put', 'patch'], 'templates/batch', [TemplateController::class, 'batchUpdate']);
        $api->match(['post'], 'templates/batch-duplicate', [TemplateController::class, 'batchDuplicate']);
        $api->match(['post'], 'templates/batch-export', [TemplateController::class, 'batchExport']);
        $api->match(['post'], 'templates/import', [TemplateController::class, 'import']);
        $api->match(['delete'], 'templates/batch', [TemplateController::class, 'batchDestroy']);
        $api->match(['put', 'patch'], 'templates/batch-deactivate', [TemplateController::class, 'batchDeactivate']);
        $api->match(['put', 'patch'], 'templates/batch-activate', [TemplateController::class, 'batchActivate']);
        $api->match(['put', 'patch'], 'templates/batch-restore', [TemplateController::class, 'batchRestore']);
        $api->resource('templates', TemplateController::class);

        $api->match(['put', 'patch'], 'template-groups/batch', [TemplateGroupController::class, 'batchUpdate']);
        $api->match(['post'], 'template-groups/batch-duplicate', [TemplateGroupController::class, 'batchDuplicate']);
        $api->match(['put', 'patch'], 'template-groups/batch-ungroup', [TemplateGroupController::class, 'batchUngroup']);
        $api->match(['put', 'patch'], 'template-groups/batch-restore', [TemplateGroupController::class, 'batchRestore']);
        $api->match(['delete'], 'template-groups/batch', [TemplateGroupController::class, 'batchDestroy']);
        $api->resource('template-groups', TemplateGroupController::class);

        $api->match(['post'], 'playlists/{playlist}/pages/generate-unique-name', [PlaylistPageController::class, 'generateUniqueName']);
        $api->match(['put', 'patch'], 'playlists/{playlist}/pages/batch', [PlaylistPageController::class, 'batchUpdate']);
        $api->match(['post'], 'playlists/{playlist}/pages/batch-duplicate', [PlaylistPageController::class, 'batchDuplicate']);
        $api->match(['delete'], 'playlists/{playlist}/pages/batch', [PlaylistPageController::class, 'batchDestroy']);
        $api->match(['put', 'patch'], 'playlists/{playlist}/pages/batch-restore', [PlaylistPageController::class, 'batchRestore']);

        //$api->resource('fields', FieldController::class);

        //$api->match(['delete'], 'ue-preset-assets/batch', [UePresetAssetController::class, 'batchDestroy']);
        //$api->resource('ue-preset-assets', UePresetAssetController::class);

        //$api->match(['put', 'patch'], 'ue-presets/batch', [UePresetController::class, 'batchUpdate']);
        //$api->match(['delete'], 'ue-presets/batch', [UePresetController::class, 'batchDestroy']);
        //$api->resource('ue-presets', UePresetController::class);

        //$api->match(['put', 'patch'], 'ue-preset-groups/batch', [UePresetGroupController::class, 'batchUpdate']);
        //$api->match(['delete'], 'ue-preset-groups/batch', [UePresetGroupController::class, 'batchDestroy']);
        //$api->resource('ue-preset-groups', UePresetGroupController::class);

        $api->resource('company-integrations', CompanyIntegrationsController::class);  //integrations per company
        $api->put('company-preferences', [CompanyPreferencesController::class, 'update']);

        $api->get('plugins', [PortaPluginController::class, 'index']);
        $api->get('releases', [PortaReleaseController::class, 'index']);

        // schedule related routes
        require __DIR__ . '/api.schedule.php';

    });
    // -- end JWT auth group

    $api->group(['middleware' => ['api.auth', 'api.auth.silently:api,api-key'], 'providers' => ['jwt', 'api-key']], function(Router $api) {
        /**
         * Let the IO server modify items
         */
        // Project
        $api->resource('projects', ProjectController::class);
        $api->match(['delete'], 'projects/{project}/playlists/batch', [ProjectPlaylistController::class, 'batchDestroy']);
        $api->resource('projects.playlists', ProjectPlaylistController::class);
        // Playlist Pages
        $api->resource('playlists.pages', PlaylistPageController::class);

        // Pages
        $api->match(['put', 'patch'], 'pages/batch-attach', [PageController::class, 'batchAttach']);
        $api->match(['put', 'patch'], 'pages/batch-detach', [PageController::class, 'batchDetach']);
        $api->match(['post'], 'pages/{page}/store-reference', [PageController::class, 'storeReference']);
        $api->resource('pages', PageController::class);

        // Playlist Page Groups
        $api->match(['put', 'patch'], 'playlists/{playlist}/page-groups/batch', [PlaylistPageGroupController::class, 'batchUpdate']);
        $api->match(['post'], 'playlists/{playlist}/page-groups/batch-duplicate', [PlaylistPageGroupController::class, 'batchDuplicate']);
        $api->match(['put', 'patch'], 'playlists/{playlist}/page-groups/batch-ungroup', [PlaylistPageGroupController::class, 'batchUngroup']);
        $api->match(['delete'], 'playlists/{playlist}/page-groups/batch', [PlaylistPageGroupController::class, 'batchDestroy']);
        $api->match(['put', 'patch'], 'playlists/{playlist}/page-groups/batch-restore', [PlaylistPageGroupController::class, 'batchRestore']);
        $api->match(['post'], 'playlists/{playlist}/page-groups/{page_group}/play-sequence', [PlaylistPageGroupController::class, 'playSequence']);
        $api->resource('playlists.page-groups', PlaylistPageGroupController::class);

        // Files
        $api->match(['delete'], 'files/batch', [FileController::class, 'batchDestroy']);
        $api->resource('files', FileController::class, ['only' => ['index', 'store', 'update']]);
        $api->get('getFile/{id}', [FileController::class, 'getFile']);
        $api->resource('file-metas', FileMetaController::class, ['only' => ['index', 'update']]);
        // Bridge will send POST requests containing both new and existing files
        $api->post('file-metas/upsert', [FileMetaController::class, 'upsert']);
        $api->delete('file-metas/upsert', [FileMetaController::class, 'bridgeDelete']);


        $api->get('playlists/{playlist}/media', [PlaylistMediaController::class, 'index']);
        $api->get('playlists', [PlaylistController::class, 'index']);

        $api->post('companies/channels/sync', [ChannelController::class, 'sync'])->name('companies.channels.sync');

        $api->post('d3', [D3Controller::class, 'D3APICall']);

        $api->get('d3-tracks', [D3Controller::class, 'getTracks']);
        $api->get('d3-transports', [D3Controller::class, 'getTransports']);
        $api->get('d3-tag-list/{track}/{type}', [D3Controller::class, 'tagList']);
        $api->get('d3-tag-list//{type}', [D3Controller::class, 'allTagList']);
        $api->get('d3-tag-list/{type}', [D3Controller::class, 'allTagList']);

        $api->get('d3-indirection-list/{type}', [D3Controller::class, 'indirectionList']);
        $api->get('d3-resource-list/{type}', [D3Controller::class, 'resourceList']);

        $api->post('d3-update-page-media-id', [D3Controller::class, 'updatePageMediaID']);

        $api->get('getD3SharedMedia/{page_id}', [PageController::class, 'getD3SharedMedia']);

        $api->post('h264', [D3Controller::class, 'convertToH264']);

        $api->get('weather-cities', [WeatherController::class, 'getCities']);
        $api->get('weather/{city}', [WeatherController::class, 'getWeather']);

        $api->get('sports-soccer-match-sample', [SportsSoccerController::class, 'matchSample']);
        $api->get('sports-soccer-match-schedule/{lang}/{date}', [SportsSoccerController::class, 'matchSchedule']);
        //$api->get('sports-soccer-match-schedule/{lang}', [SportsSoccerController::class, 'matchScheduleToday']);
        $api->get('sports-soccer-match-schedule/{lang}', [SportsSoccerController::class, 'matchScheduleList']);
        $api->get('sports-soccer-match-data/{lang}/{matchId}', [SportsSoccerController::class, 'matchData']);
        $api->get('sports-soccer-match-data/{lang}/{matchId}/{data}', [SportsSoccerController::class, 'matchSingleData']);
        $api->get('sports-soccer-match-list', [SportsSoccerController::class, 'matchList']);
        $api->get('sports-soccer-match-lineups/{matchId}', [SportsSoccerController::class, 'matchLineups']);
        $api->get('sports-soccer-standings-stages/{lang}', [SportsSoccerController::class, 'standingsStages']);
        $api->get('sports-soccer-standings/{lang}/{stage}', [SportsSoccerController::class, 'standings']);
        $api->get('sports-soccer-venues/{lang}', [SportsSoccerController::class, 'venues']);
        $api->get('sports-soccer-venue/{lang}/{venueId}', [SportsSoccerController::class, 'venueData']);
        $api->get('sports-soccer-teams/{lang}', [SportsSoccerController::class, 'teams']);
        $api->get('sports-soccer-players/{lang}/{tournament_team}', [SportsSoccerController::class, 'players']);
        $api->get('sports-soccer-players/{lang}', [SportsSoccerController::class, 'allPlayers']);
        $api->get('sports-soccer-player-stats/{lang}/{tournament_player}', [SportsSoccerController::class, 'playerStats']);
        $api->get('sports-soccer-player-stats/{lang}/{tournament_player}/{data}', [SportsSoccerController::class, 'playerStatsData']);  //selected single data
        $api->get('sports-soccer-bracket/{lang}/{tournament}', [SportsSoccerController::class, 'bracket']);

        $api->match(['post', 'get'], 'google-oauth', [GoogleSheetController::class, 'googleOauth']);
        $api->match(['post', 'get'], 'delete-google-oauth', [GoogleSheetController::class, 'deleteGoogleOauth']);
        $api->post('get-google-oauth', [GoogleSheetController::class, 'getGoogleOauth']);

        $api->get('google-sheet/{spreadsheet_id}/{sheet_id}/{google_fields}', [GoogleSheetController::class, 'getSheetVals']);
        $api->get('google-sheet/{spreadsheet_id}/getSheets', [GoogleSheetController::class, 'getSheets']);
        $api->get('google-sheet/getSpreadsheets', [GoogleSheetController::class, 'getSpreadsheets']);
        $api->get('google-sheet-titles/{spreadsheet_id}/{sheet_id}', [GoogleSheetController::class, 'getTitles']);
        $api->get('google-sheet-column-rows/{spreadsheet_id}/{sheet_id}/{column}', [GoogleSheetController::class, 'getColumnRows']);
        $api->get('google-sheet-column-rows-by-order/{spreadsheet_id}/{sheet_id}/{column}/{order}', [GoogleSheetController::class, 'getColumnRowsByOrder']);
        $api->get('google-sheet-all-cols-rows/{spreadsheet_id}/{sheet_id}', [GoogleSheetController::class, 'getAllColsRows']);

        $api->get('google-drive/getXMLs', [GoogleDriveXMLController::class, 'getXMLs']);
        $api->get('google-drive/getXMLRows/{xml_id}', [GoogleDriveXMLController::class, 'getXMLRows']);
        $api->get('google-drive/getXMLTags/{xml_id}', [GoogleDriveXMLController::class, 'getXMLTags']);
        $api->get('google-drive/getXMLTagVals/{xml_id}/{tag}', [GoogleDriveXMLController::class, 'getXMLTagVals']);
        $api->get('google-drive/getXMLTagVal/{xml_id}/{tag}/{index}', [GoogleDriveXMLController::class, 'getXMLTagVal']);
        $api->get('google-drive/getXMLAllTagsVals/{xml_id}/{index}', [GoogleDriveXMLController::class, 'getXMLAllTagsVals']);
        $api->get('google-drive/getXMLTagValsByOrder/{xml_id}/{tag}/{order}', [GoogleDriveXMLController::class, 'getXMLTagValsByOrder']);
        $api->get('google-drive/getXMLAllRowsTags/{xml_id}', [GoogleDriveXMLController::class, 'getXMLAllRowsTags']);

        $api->match(['post'], 'workflows/run-filter-steps', [WorkflowController::class, 'runFilterSteps']);
        $api->match(['post'], 'workflows/{workflow}/workflow-run-logs', [WorkflowController::class, 'workflowRunLogStore']);
        $api->match(['post'], 'workflows/{workflow}/run', [WorkflowController::class, 'run']);
        $api->match(['post'], 'workflows/{workflow}/revert', [WorkflowController::class, 'revert']);
        $api->resource('workflows', WorkflowController::class);
    });

    $api->group(['middleware' => ['api.auth.silently:api-health-secret'], 'providers' => ['api-health-secret']], function(Router $api) {
        // Oh Dear App health checks endpoint
        $api->get('health', HealthCheckJsonResultsController::class);
    });

    $api->group(['middleware' => ['api.auth.silently:s3-plugins']], function(Router $api) {
        $api->post('plugins', [PortaPluginController::class, 'store']);
    });

});
