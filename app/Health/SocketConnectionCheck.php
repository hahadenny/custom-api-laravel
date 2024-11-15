<?php

namespace App\Health;

use App\Services\Monitoring\SocketConnectionService;
use Illuminate\Support\Facades\Log;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Is our default connection working?
 */
class SocketConnectionCheck extends Check
{
    public const COMMAND_ALIAS = 'socket_conn';
    public const NAME = 'SocketConnection';
    public const CACHE_KEY = 'socket_check';
    private const CONNECTION_TIMEOUT = 1;

    public function __construct(protected SocketConnectionService $connectionService)
    {
        parent::__construct();
    }

    public function getCacheKey() : string
    {
        return static::CACHE_KEY;
    }

    /**
     * @throws \Exception
     */
    public function run() : Result
    {
        $connections = config('services.socketio.on-prem.connections');
        $connectionName = config('services.socketio.on-prem.default');
        $defaultConfig = $connections[$connectionName];
        $host = $connections[$connectionName]['host'];
        $port = $connections[$connectionName]['port'];

        try {
            $connectionResult = $this->connectionService->checkConnection($connectionName);
            $result = Result::make()
                            ->meta([
                                       'connection_name'           => $connectionName,
                                       'default_connection_config' => $defaultConfig,
                                   ]);

            if($connectionResult) {
                $result->shortSummary('Default socket connection "' . $connectionName . '" is working. (host: ' . $host . ', port: ' . $port . ')');
                return $result->ok();
            }

            $result->shortSummary('Default socket connection "' . $connectionName . '" is NOT working. (host: ' . $host . ', port: ' . $port . ')');
            Log::error('!! Socket connection ("' . $connectionName . '") is not working --> host:' . $host . ', port: ' . $port);

            return $result->failed('Socket connection ("' . $connectionName . '") failed.');

        } catch(\Exception $e) {
            $result = Result::make()
                            ->meta([
                                       'connection_name'           => $connectionName,
                                       'default_connection_config' => $defaultConfig,
                                   ])
                            ->shortSummary('Socket check encountered error when checking for connection "' . $connectionName . '".');
            Log::error('!! Socket check encountered error when checking for connection "' . $connectionName . '". --> ' . $e->getMessage());
            return $result->failed($e->getMessage());
        }
    }
}
