<?php

namespace App\Console\Commands\Health;

use App\Traits\RunsHealthCheckCommand;
use Illuminate\Support\Facades\Log;
use Spatie\Health\Commands\RunHealthChecksCommand;

class RunManyHealthChecksCommand extends RunHealthChecksCommand
{
    use RunsHealthCheckCommand;

    protected $signature = 'health:many-checks {check?*} {--do-not-store-results} {--no-notification} {--fail-command-on-failing-check}';

    protected $description = 'Run a set of health checks';

    /**
     * @throws \Exception
     */
    public function handle(): int
    {
        $checksArg = $this->argument('check') ?? $this->ask('Run which checks?');

        $this->info('Running health checks...');

        $results = collect();
        foreach($checksArg as $checkArg) {
            $check = $this->determineIntendedCheck($checkArg);
            try {
                // don't need to determine if it should run or not because this check was specifically requested
                $results->push($this->runCheck($check));
            } catch (\Exception $e){
                // primary conn failure will interrupt with exception if we don't catch it
                // so we can broadcast the data later
                Log::error($e->getMessage());
            }
        }

        return $this->handleHealthCheckResults($results);
    }
}
