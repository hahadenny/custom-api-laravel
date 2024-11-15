<?php

namespace App\Providers;

use App\Health\DatabaseCheck;
use App\Health\GaleraClusterCheck;
use App\Health\GaleraNodeStatusCheck;
use App\Health\GaleraReplicationCheck;
use App\Health\MysqlClusterCheck;
use App\Health\MysqlNodeStatusCheck;
use App\Health\PrimaryDatabaseConnectionCheck;
use App\Health\SlowQueriesCheck;
use App\Health\SocketConnectionCheck;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseConnectionCountCheck;
use Spatie\Health\Checks\Checks\DatabaseSizeCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class HealthCheckServiceProvider extends ServiceProvider
{
    public function register() : void
    {

    }

    public function boot() : void
    {
        $checks_arr = [
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(60)
                ->failWhenUsedSpaceIsAbovePercentage(80)
            ,
            // connected to DB
            DatabaseCheck::new(),
            // make sure the default redis connection `config('database.redis.default');` is working
            RedisCheck::new(),
            // NOTE: doctrine/dbal package is required
            DatabaseConnectionCountCheck::new()
                                        ->warnWhenMoreConnectionsThan(10)
                                        ->failWhenMoreConnectionsThan(50),
            // NOTE: doctrine/dbal package is required
            DatabaseSizeCheck::new()
                             ->failWhenSizeAboveGb(errorThresholdGb: 5.0),
            // check if config, route and events are cached
            // OptimizedAppCheck::new(),
            // check the application can connect to the default cache driver and read/write to the cache keys
            CacheCheck::new(),
            // make sure queries are faster than 1s
            SlowQueriesCheck::new()
        ];

        if (config('app.url') === 'https://api.porta.solutions') {
            // fails when app environment (APP_ENV) is not set to 'production'
            $checks_arr [] = EnvironmentCheck::new();
        }

        if (config('app.env') === 'production') {
            // fails when debug mode (APP_DEBUG) is true
            $checks_arr [] = DebugModeCheck::new();
        }

        if (config('app.onprem')){
            $checks_arr [] = SocketConnectionCheck::new();
        }

        if (config('app.onprem') && config('database.cluster.onprem_replication_enabled')) {
            // only register the check on-prem
            $checks_arr [] = PrimaryDatabaseConnectionCheck::new();
            $checks_arr [] = MysqlClusterCheck::new();
            $checks_arr [] = MysqlNodeStatusCheck::new();
        } elseif( !config('app.onprem') && config('app.env') === 'production') {
            // Only do galera cluster checks in production in the cloud

            // Make sure a node is connected and synced to the cluster
            $checks_arr [] = GaleraNodeStatusCheck::new();
            // Make sure a node is receiving and processing updates from the cluster write-sets
            $checks_arr [] = GaleraReplicationCheck::new();
            // Check cluster size and make cluster related status fields available to view in ohdear.app
            $checks_arr [] = GaleraClusterCheck::new();
        }

        /*
         * Other Checks:
         * @see https://spatie.be/docs/laravel-health/v1/available-checks/overview

            // doctrine/dbal package is required
            DatabaseTableSizeCheck::new()
                              ->table('your_table_name', maxSizeInMb: 1_000)
                              ->table('another_table_name', maxSizeInMb: 2_000),

            // reports a warning when Horizon is paused, and a failure when Horizon is not running
            HorizonCheck::new(),

            RedisMemoryUsageCheck::new()->failWhenAboveMb(1000),
        */

        // register these checks
        Health::checks($checks_arr);
    }
}
