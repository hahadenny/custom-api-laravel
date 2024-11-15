<?php

namespace App\Jobs;

use App\Models\Schedule\ScheduleSet;
use App\Services\Schedule\Helpers\LoopModeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * "Stop" was clicked for scheduler loop mode
 */
class StopLoopJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ScheduleSet $scheduleSet)
    {
        /**
         * The name of the queue connection the job should be sent to.
         */
        $this->connection = 'sync';
    }

    public function handle(LoopModeService $loopModeService)
    {
        $loopModeService->stop($this->scheduleSet);
    }
}
