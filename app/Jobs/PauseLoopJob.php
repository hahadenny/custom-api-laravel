<?php

namespace App\Jobs;

use App\Models\Schedule\ScheduleSet;
use App\Models\User;
use App\Services\Schedule\Helpers\LoopModeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * "Pause" was clicked for scheduler loop mode
 */
class PauseLoopJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ScheduleSet $scheduleSet, public User $user, public $user_timezone)
    {
        /**
         * The name of the queue connection the job should be sent to.
         */
        $this->connection = 'sync';
    }

    public function handle(LoopModeService $loopModeService)
    {
        $loopModeService->pause($this->scheduleSet, $this->user, $this->user_timezone);
    }
}
