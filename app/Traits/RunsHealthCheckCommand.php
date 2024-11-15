<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Spatie\Health\Checks\Check;
use Spatie\Health\Health;

trait RunsHealthCheckCommand
{
    /**
     * @param string $checkArg - short COMMAND_ALIAS const of the health check class, the class base name, or the FQCN
     *
     * @return Check|null
     * @throws \Exception
     */
    protected function determineIntendedCheck(string $checkArg) : ?Check
    {
        $availableChecks = app(Health::class)->registeredChecks();
        $matchingCheck = $availableChecks->first(function (Check $check) use ($checkArg) {
            return class_basename($check) === class_basename($checkArg) || (defined($check::class."::COMMAND_ALIAS") && $check::COMMAND_ALIAS === $checkArg);
        });

        if(!empty($matchingCheck)){
            return $matchingCheck;
        }

        throw new \Exception("No matching health check found. Are you sure it is registered in the HealthCheckServiceProvider?");
    }

    protected function handleHealthCheckResults(Collection $results)
    {
        if (! $this->option('no-notification')) {
            $this->sendNotification($results);
        }

        if (! $this->option('do-not-store-results')) {
            $this->storeResults($results);
        }

        $this->line('');
        $this->info('All done!');

        return $this->determineCommandResult($results);
    }
}
