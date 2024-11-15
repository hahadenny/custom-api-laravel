<?php

namespace App\Console;

use App\Console\Commands\Health\RunDatabaseConnectionChecksCommand;
use App\Health\SocketConnectionCheck;
use App\Jobs\CheckSchedulerPlayoutsJob;
use App\Services\Monitoring\DatabaseConnectionService;
use App\Traits\CachesCheckResults;
use Illuminate\Console\Scheduling\Schedule as AppSchedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    use CachesCheckResults;

    /**
     * Define the application's schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(AppSchedule $schedule)
    {
        // #############################################################################
        // !! NOTE !!
        // when translated by laravel-timer, everyMinute() will be run every 1 second
        // adjust frequency in laravel-timer.timer `OnUnitActiveSec`
        // #############################################################################

        if(config('app.onprem')){
            // run socket check

            // NOTE: will effectively be run every second
            $schedule->command('socket:check --do-not-store-results --no-notification --fail-command-on-failing-check')
                     ->everyMinute()
                     ->when(function() {
                         // If the last failed check was more than 5 minutes ago, then
                         // skip the check to avoid spamming the logs with errors
                         $cacheData = Cache::get(SocketConnectionCheck::CACHE_KEY);
                         return $this->checkShouldRun($cacheData);
                     })
                     ->runInBackground();
                    // ->withoutOverlapping() causes the command to be skipped infinitely sometimes
        }

        # TODO: handle on-prem backups without replication
        if(config('app.onprem') && config('database.cluster.onprem_replication_enabled')) {

            // run DB checks to update front end status
            // NOTE: will effectively be run every 2 seconds
            $schedule->command('db:checks --no-notification --fail-command-on-failing-check')
                     ->everyMinute()
                     ->when(function(){
                         if(Carbon::now()->second % 2 !== 0){
                             return false;
                         }

                         // If the last failed check was more than 5 minutes ago, then
                         // skip the check to avoid spamming the logs with errors
                         $cacheData = Cache::get(RunDatabaseConnectionChecksCommand::CACHE_KEY);
                         return $this->checkShouldRun($cacheData);
                     })
                     ->runInBackground()
                    // cache the check info here because db:checks is a COMMAND running many CHECKS in one,
                    // and we want to cache all of them together in one record
                    ->before(function(){
                        $this->cacheCheckStartNowIfEmpty(RunDatabaseConnectionChecksCommand::CACHE_KEY);
                    })
                    ->onSuccess(function (){
                        $this->cacheCheckSuccessNow(RunDatabaseConnectionChecksCommand::CACHE_KEY);
                    })
                    ->onFailure(function (){
                        $this->cacheCheckFailureNow(RunDatabaseConnectionChecksCommand::CACHE_KEY);
                    });
                    // ->withoutOverlapping(); // this will prevent more checks from running when there are many errors

            $lastBackUpCacheKey = 'backup-last-ran-at';

            // clean out backups every day @ 1am
            $schedule->command('backup:clean')
                     ->daily()
                     ->at('01:00')
                     ->runInBackground()
                     ->withoutOverlapping();

            // Backup the database every 3 hours (if this machine meets backup requirements)
            $schedule->command('backup:run --only-db --disable-notifications')
                     ->everyThreeHours()
                     ->when(function() use($lastBackUpCacheKey) {

                         $dbConnService = new DatabaseConnectionService();

                         // make sure the laravel-timer doesn't cause the backup to run every second
                         // for the first minute of every third hour
                         $lastBackUp = Cache::get($lastBackUpCacheKey);
                         return $dbConnService->shouldRunBackup() && ($lastBackUp === null || $lastBackUp->diffInMinutes(now()) >= 180);
                     })->runInBackground()
                      ->withoutOverlapping()
                      ->onSuccess(function () use ($lastBackUpCacheKey){
                          // update the cache when finished backing up
                          Cache::put($lastBackUpCacheKey, now());
                      });

        } elseif(!config('app.onprem') && config('services.scheduler.enabled')) {
            // TODO: disable scheduler on-prem in a proper way
            $schedule->job(new CheckSchedulerPlayoutsJob())->everyMinute();
        }

        if(!empty(config('telescope.enabled'))){
            $schedule->command('telescope:prune')->daily();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    protected function checkShouldRun(string|array|null $cacheData, int $minutesSince=1) : bool
    {
        // always run; this method is causing too many problems right now when checking the cache
        // TODO: look into cache timing and problems
        return true;

        if($cacheData === null) {
            Log::debug("<< Cache data was null in checkShouldRun. Returning true. >>");
            return true;
        }

        if(!isset($cacheData['lastFailedAt'])){
            // check has never failed
            return true;
        }

        if(
            ((isset($cacheData['lastSuccessfulAt']) && !$this->checkRecentlySucceeded($cacheData, $minutesSince))
                || !isset($cacheData['lastSuccessfulAt']))
            && $this->checkRecentlyFailed($cacheData, $minutesSince)
        ){
            // succeeded long ago, OR never succeeded at all
            // AND
            // failed recently

            // This indicates that something went wrong and is not recovering; we should
            // stop checking for a while so we don't spam processes or logs.
            return false;
        }

        // check succeeded recently and/or failed recently
        return true;
    }

    /**
     * Determine if the last health check in the cached data failed
     * (or was started) less than `$minutesSince` minutes ago
     *
     * @param string|array|null $cacheData
     * @param int   $minutesSince
     *
     * @return bool
     */
    protected function checkRecentlyFailed(string|array|null $cacheData, int $minutesSince=1) : bool
    {
        if($cacheData === null) {
            Log::debug("<< Cache data was null in checkRecentlyFailed. Returning false. >>");
            return false;
        }

        $cacheData = is_string($cacheData) ? json_decode($cacheData, true) : $cacheData;

        if(isset($cacheData['lastFailedAt'])) {
            // Log::debug("Last failed at: " . $cacheData['lastFailedAt']);
            return $this->isValueWithinGivenMinutes($cacheData['lastFailedAt'], $minutesSince);
        }

        // check has never failed
        return false;
    }

    /**
     * Determine if the last health check in the cached data succeeded
     * (or was started) less than `$minutesSince` minutes ago
     *
     * @param string|array|null $cacheData
     * @param int   $minutesSince
     *
     * @return bool
     */
    protected function checkRecentlySucceeded(string|array|null $cacheData, int $minutesSince=1) : bool
    {
        if($cacheData === null) {
            Log::debug(" >> Cache data was null in checkRecentlySucceeded. Returning false. << ");
            return false;
        }

        $cacheData = is_string($cacheData) ? json_decode($cacheData, true) : $cacheData;

        if(isset($cacheData['lastSuccessfulAt'])) {
            // Log::debug("Last successful at: " . $cacheData['lastSuccessfulAt']);
            return $this->isValueWithinGivenMinutes($cacheData['lastSuccessfulAt'], $minutesSince);
        }

        // check has never succeeded
        return false;
    }

    /**
     * @param string $value - Carbon parseable datetime string
     * @param int    $minutesSince - number of minutes to check the `$value` occurs within
     *
     * @return bool
     */
    protected function isValueWithinGivenMinutes(string $value, int $minutesSince=5) : bool
    {
        $lastChanged = Carbon::parse($value);
        return $lastChanged->diffInMinutes(now()) < $minutesSince;
    }
}
