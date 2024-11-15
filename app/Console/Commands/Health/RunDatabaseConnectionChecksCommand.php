<?php

namespace App\Console\Commands\Health;

/**
 * Run several database related health checks
 */
class RunDatabaseConnectionChecksCommand extends RunManyHealthChecksCommand
{
    public const CACHE_KEY = 'db_checks';

    protected $signature = 'db:checks {--do-not-store-results} {--no-notification} {--fail-command-on-failing-check}';

    protected $description = 'Run database connection health checks';

    public function handle(): int
    {
        $replicationChecks = [];
        if( !config('app.onprem') && config('app.env') === 'production') {
            $replicationChecks = [
                'GaleraClusterCheck',
                'GaleraNodeStatusCheck',
                'GaleraReplicationCheck',
            ];
        } elseif(config('app.onprem') && config('database.cluster.onprem_replication_enabled')){
            $replicationChecks = [
                'PrimaryDatabaseConnectionCheck',
                'MysqlClusterCheck',
                'MysqlNodeStatusCheck'
            ];
        }
        return $this->call('health:many-checks', [
            'check' => [
                'DatabaseCheck',
                ...$replicationChecks,
            ],
            '--do-not-store-results' => $this->option('do-not-store-results') ?? false,
            '--no-notification' => $this->option('no-notification') ?? false,
            '--fail-command-on-failing-check' => $this->option('fail-command-on-failing-check') ?? false,
        ]);
    }
}
