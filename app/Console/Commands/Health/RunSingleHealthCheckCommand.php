<?php

namespace App\Console\Commands\Health;

use App\Traits\RunsHealthCheckCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;

class RunSingleHealthCheckCommand extends RunHealthChecksCommand
{
    use RunsHealthCheckCommand;

    protected $signature = 'health:single-check {check?} {--do-not-store-results} {--no-notification} {--fail-command-on-failing-check}';

    protected $description = 'Run a single health check';

    public function handle(): int
    {
        $checkArg = $this->argument('check') ?? $this->ask('Run which check? Enter its COMMAND_ALIAS or class basename');

        $check = $this->determineIntendedCheck($checkArg);

        $this->info('Running health check...');
        $result = $this->runCheck($check);

        return $this->handleHealthCheckResults(collect([$result]));
    }
}
