<?php

namespace App\Console\Commands\Health;

/**
 * Run several database related health checks
 */
class RunDatabaseChecksCommand extends RunManyHealthChecksCommand
{
    public const CACHE_KEY = 'db_misc_checks';

    protected $signature = 'db:misc-checks {--do-not-store-results} {--no-notification} {--fail-command-on-failing-check}';

    protected $description = 'Run miscellaneous database health checks';

    public function handle(): int
    {
        return $this->call('health:many-checks', [
            'check' => [
                'DatabaseCheck',
                'SlowQueriesCheck',
                'DatabaseSizeCheck',
                'DatabaseConnectionCountCheck'
            ],
            '--do-not-store-results' => $this->option('do-not-store-results') ?? false,
            '--no-notification' => $this->option('no-notification') ?? false,
            '--fail-command-on-failing-check' => $this->option('fail-command-on-failing-check') ?? false,
        ]);
    }
}
