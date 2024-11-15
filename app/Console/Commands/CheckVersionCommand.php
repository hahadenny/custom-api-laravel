<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CheckVersionCommand extends Command
{
    protected $signature = 'check:version';

    protected $description = 'Display Version Information';

    public function handle() : int
    {
        $version_date = config('app.versioning.version_date');
        $redisVersion = 'ERROR -- NOT AVAILABLE';
        try{
            $redisInfo = Redis::info();
            $redisVersion = $redisInfo['redis_version'];
        } catch (\Throwable $e) {
            Log::warning('Redis is not available');
        }
        $this->table(
            ['Name', 'Info'],
            [
                ['Porta Machine', config('app.versioning.machine')],
                ['Porta Version', config('app.versioning.version')],
                ['Porta Version Number', config('app.versioning.version_number')],
                ['Porta Build', config('app.versioning.build')],
                ['Porta Version Date', isset($version_date)
                    ? Carbon::createFromFormat('Ymd.His.T', $version_date)->toDateTimeString()
                    : null
                ],
                ['Laravel', app()->version()],
                ['PHP', phpversion()],
                ['MySQL', \DB::select('SELECT VERSION() AS version')[0]->version],
                ['Redis', $redisVersion],
                ['OS', php_uname()],
            ]
        );

        return Command::SUCCESS;
    }
}
