<?php

namespace App\Health;

use App\Services\Monitoring\DatabaseConnectionService;
use Illuminate\Support\Facades\Log;
use Spatie\Health\Checks\Checks\DatabaseCheck as BaseDatabaseCheck;
use Spatie\Health\Checks\Result;

/**
 * Check that the database is connected.
 * Include connection & host names with returned meta values
 */
class DatabaseCheck extends BaseDatabaseCheck
{
    public const COMMAND_ALIAS = 'db';
    private const CONNECTION_TIMEOUT = 2;

    public const NAME = 'Database';

    public function __construct(protected DatabaseConnectionService $connectionService)
    {
        parent::__construct();
    }

    public function run() : Result
    {
        $connectionName = $this->connectionName ?? $this->getDefaultConnectionName();
        $hostName = $this->getHostName();

        if(!isset($hostName)){
            $result = Result::make()->meta([
               'connection_name' => $connectionName,
               'host_name'       => $hostName,
           ])->shortSummary("Connection '".$connectionName."' has no host name");
            return $result->failed("No host for connection '$connectionName'");
        }

        $result = Result::make()->meta([
            'connection_name' => $connectionName,
            'host_name'       => $hostName,
        ]);

        $connectionOk = $this->connectionService->checkConnection($connectionName, self::CONNECTION_TIMEOUT);
        if($connectionOk){
            return $result->shortSummary("Connected to '".$hostName."'")->ok();
        }

        Log::error("Could not reach the database host machine for connection '$connectionName' on host '$hostName'.");

        return $result->shortSummary("Could not reach '".$hostName."'")->failed("Could not reach the database host machine for connection '$connectionName' on host '$hostName'.");
    }

    protected function getHostName() : string|null
    {
        $connectionName = $this->connectionName ?? $this->getDefaultConnectionName();
        return config('database.connections.' . $connectionName . '.host');
    }
}
