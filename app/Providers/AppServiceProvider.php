<?php

namespace App\Providers;

use App\Api\V1\Controllers\PingController;
use App\Health\GaleraClusterCheck;
use App\Health\GaleraNodeStatusCheck;
use App\Health\GaleraReplicationCheck;
use App\Health\MysqlClusterCheck;
use App\Health\MysqlNodeStatusCheck;
use App\Health\SlowQueriesCheck;
use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Services\Monitoring\ClusterChecksService;
use App\Services\Monitoring\GaleraNodeService;
use App\Services\Monitoring\MysqlNodeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        JsonResource::withoutWrapping();

        ConvertEmptyStringsToNull::skipWhen(function (Request $request) {
            return $request->is('api/templates', 'api/templates/*');
        });

        $this->app['Dingo\Api\Exception\Handler']->register(function (AuthorizationException $exception) {
            return response(['error' => ['message' => $exception->getMessage(), 'status_code' => 403]], 403);
        });

        Relation::morphMap([
            'channel' => Channel::class,
            'channel_group' => ChannelGroup::class,
        ]);

        Password::defaults(function () {
            return Password::min(8)->numbers()->symbols();
        });

        $this->app->when([
            PingController::class,
            SlowQueriesCheck::class,
            GaleraReplicationCheck::class,
            GaleraClusterCheck::class,
            GaleraNodeStatusCheck::class,
            MysqlClusterCheck::class,
            MysqlNodeStatusCheck::class
        ])
        ->needs(ClusterChecksService::class)
        ->give(function($app){
            return config('app.onprem')
                ? $app->make(MysqlNodeService::class)
                : $app->make(GaleraNodeService::class);
        });
    }
}
