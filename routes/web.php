<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

if(config('app.onprem')){
    // allow on-prem to view health dashboard
    Route::get('health', App\Http\Controllers\HealthCheckDetailedResultsController::class);

    Route::get('/socketio-adminui', function (Request $request) {
        return view('socketio-adminui');
    });
}



/*Route::get('/', function (Request $request) {
    $d3 = new \App\Services\Engines\D3\D3Event();
    $httpResponse = $d3->makeBridgeHttpRequest(
        "/api/v1/resources?type=VideoClip)",
        host: '192.168.50.183',
        port: 80,
        // path: '/api/v1/resources?type=VideoClip'
        // path: '/api/experimental/sockpuppet/patches'
    );

    dump('=====',$httpResponse);

    // return view('welcome');
});*/
