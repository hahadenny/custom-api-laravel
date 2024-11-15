<?php

namespace App\Listeners;

use App\Events\DatabaseStatus;
use App\Health\MysqlClusterCheck;
use App\Health\PrimaryDatabaseConnectionCheck;
use App\Services\Monitoring\DatabaseConnectionService;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;

/**
 * If a finished console command was the database health checks command, update the database status
 */
class RunManyHealthChecksEndedEventListener
{
    // base name of the health check class
    public readonly string     $checkClassBaseName;
    public readonly Result     $result;
    public readonly Check      $check;
    public readonly string|int $status;
    public readonly string     $finishedAt;

    public function __construct(protected DatabaseConnectionService $connService) {
        $this->finishedAt = now()->format('U');
    }

    /**
     * Called when this listener is triggered
     *
     * @throws \Exception
     */
    public function handle(CommandFinished $event) : void
    {
        $healthJson = json_decode(Storage::get('health.json'), true);

        if(!isset($healthJson['finishedAt'])){
            // no health checks were run successfully
            // usually this occurs when the server worker is running these commands before the application is ready
            Log::warning("No health checks were run successfully (for RunManyHealthChecksEndedEventListener -- ".now().")");
            return;
        }

        // we want to process health checks that finished at the same time
        // that this listener was triggered
        if($this->finishedAt != $healthJson['finishedAt'] || !$this->wasAllDatabaseChecks($healthJson['checkResults'])) {
            return;
        }

        $statusKeyVals = $this->parseChecksStatus($healthJson);

        // only broadcast status if it has changed, so we avoid spamming the socket server logs
        if(Cache::get('last-db-status') === $statusKeyVals['statusSummary']){
            return;
        }

        Cache::put('last-db-status', $statusKeyVals['statusSummary'], now()->addMinutes(10));

        $healthJson = $this->appendJsonResults($healthJson, $statusKeyVals);

        // Let Porta UI know about the database status
        DatabaseStatus::dispatch($healthJson);
    }

    protected function wasAllDatabaseChecks($checkResults) : bool
    {
        foreach($checkResults as $checkResult){
            if(!$this->wasDatabaseCheck($checkResult['name'])) {
                return false;
            }
        }
        return true;
    }

    protected function wasDatabaseCheck(string $checkName) : bool
    {
        if(Str::contains($checkName, ['Database', 'Queries', 'Query', 'Cluster', 'Node', "Replication"], true)) {
            return true;
        }
        return false;
    }

    /**
     * Parse the check results array to determine the status color and summary that should be displayed
     *
     * @param array $jsonResults - the check results array
     *
     * @return array - the key value pairs to append to the check results array in order to
     *                  indicate the status color and summary
     */
    private function parseChecksStatus(array $jsonResults) : array
    {
        $appendKeyValPairs = [];
        foreach($jsonResults['checkResults'] as $checkResult){
            // ray($checkResult['name'] . " --> " . $checkResult['status'])->label('$checkResult[\'status\']');
            if($checkResult['status'] === Status::ok()->value){
                continue;
            }

            // todo: check PrimaryDatabaseConnectionCheck name
            if($checkResult['name'] === PrimaryDatabaseConnectionCheck::NAME){
                // takes priority --> database connection to primary failed
                $appendKeyValPairs = [
                    'statusColor' => Status::failed()->getSlackColor(),
                    'statusSummary' => Status::failed()->label,
                ];
                break;
            } elseif($checkResult['name'] === MysqlClusterCheck::NAME) {
                // takes next priority --> database connection failed
                $appendKeyValPairs = [
                    'statusColor' => Status::from($checkResult['status'])->getSlackColor(),
                    'statusSummary' => Status::from($checkResult['status'])->label,
                ];
                break;
            }
        }

        return empty($appendKeyValPairs)
            ? ['statusColor' => Status::ok()->getSlackColor(), 'statusSummary' => Status::ok()->label]
            : $appendKeyValPairs;
    }


    /**
     * Append key value pairs to the checkResults array
     *
     * @param array $jsonResults
     * @param array $keyValPairs - array of key value pairs to append, i.e., [$key => $val, $key2 => $val2]
     *
     * @return array
     */
    private function appendJsonResults(array $jsonResults, array $keyValPairs) : array
    {
        foreach($keyValPairs as $key => $val){
            $jsonResults['checkResults'][$key] = $val;
        }
        return $jsonResults;
    }
}
