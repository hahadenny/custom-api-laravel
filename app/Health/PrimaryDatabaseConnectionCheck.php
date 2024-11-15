<?php

namespace App\Health;

use App\Services\Monitoring\DatabaseConnectionService;
use Illuminate\Support\Facades\Log;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Is our default connection the primary connection?
 */
class PrimaryDatabaseConnectionCheck extends Check
{
    public const COMMAND_ALIAS = 'prim_db_conn';
    public const CACHE_KEY = 'prim_db_conn';
    public const NAME = 'PrimaryDatabaseConnection';

    public function __construct(protected DatabaseConnectionService $connectionService)
    {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function run() : Result
    {
        $connectionName = config('database.default');
        $defaultConfig = config('database.connections.' . $connectionName);
        // don't log the creds
        unset($defaultConfig['username']);
        unset($defaultConfig['password']);

        try {
            $primaryConnectionName = $this->connectionService->getPrimaryConnectionName($connectionName);
            $defaultIsPrimary = $this->connectionService->isPrimaryConnection($connectionName, $primaryConnectionName);
            $primaryConfig = config('database.connections.' . $primaryConnectionName);
            // don't log the creds
            unset($primaryConfig['username']);
            unset($primaryConfig['password']);


            $result = Result::make()
                            ->meta([
                                       'online_primary_name'       => $primaryConnectionName,
                                       'connection_name'           => $connectionName,
                                       'default_connection_config' => $defaultConfig,
                                       'primary_connection_config' => $primaryConfig,
                                   ]);

            if($defaultIsPrimary) {
                $primHost = config('database.connections.' . $primaryConnectionName . '.host');
                $primPort = config('database.connections.' . $primaryConnectionName . '.port');
                $result->shortSummary('Default connection "' . $connectionName . '" is the primary. (host: ' . $primHost . ', port: ' . $primPort . ')');
                return $result->ok();
            }

            $result->shortSummary('Primary database is NOT the default connection ("' . $connectionName . '").');
            Log::warning('Primary database is NOT the default connection ("' . $connectionName . '") --> default host:' . $defaultConfig['host'] . ', port: ' . $defaultConfig['port'] . ' -- primary: ' . json_encode($primaryConnectionName));

            return $result->failed('Primary database is NOT the default connection ("' . $connectionName . '").');

        } catch(\Exception $e) {
            $result = Result::make()
                            ->meta([
                                       'online_primary_name'       => null,
                                       'connection_name'           => $connectionName,
                                       'default_connection_config' => $defaultConfig,
                                       'primary_connection_config' => null,
                                   ])
                            ->shortSummary('Queried for online primary (and failed) for connection "' . $connectionName . '".');
            Log::warning('Queried for online primary (and failed) for connection "' . $connectionName . '". --> ' . $e->getMessage());
            return $result->failed($e->getMessage());
        }
    }
}
