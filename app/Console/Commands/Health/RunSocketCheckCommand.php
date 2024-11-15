<?php

namespace App\Console\Commands\Health;

/**
 * Run on-prem socket health check
 */
class RunSocketCheckCommand extends RunSingleHealthCheckCommand
{
    protected $signature = 'socket:check {--do-not-store-results} {--no-notification} {--fail-command-on-failing-check}';
    protected $description = 'Run socket health check';

    public function handle(): int
    {
        return $this->call('health:single-check', [
            'check' => 'SocketConnectionCheck',
            '--do-not-store-results' => $this->option('do-not-store-results') ?? false,
            '--no-notification' => $this->option('no-notification') ?? false,
            '--fail-command-on-failing-check' => $this->option('fail-command-on-failing-check') ?? false,
        ]);
    }
}
