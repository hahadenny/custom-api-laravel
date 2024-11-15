<?php

namespace App\Jobs;

use App\Events\ScheduleUIStatusChanged;
use App\Models\Schedule\ScheduleSet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Update page status in schedule listing UI. All status types are sent together
 */
class UpdateUIStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        // selected ScheduleSet in the scheduler listing
        public int|ScheduleSet     $scheduleSet,
        // array of listing ids for playout items with any status
        public array               $data
        /*[
            'playing' => [2, 10, 43],
            'next' => [3, 11, 44],
            'paused' => [2, 10, 43, 3, 11, 44],
        ]*/
    )
    {
        /**
         * The name of the queue connection the job should be sent to.
         */
        $this->connection = 'sync';

        $this->scheduleSet = $scheduleSet instanceof ScheduleSet ? $scheduleSet : ScheduleSet::findOrFail($scheduleSet);

        // if ( !class_exists($status) || !is_subclass_of($status, PlayoutState::class)) {
        //     throw new ValueError("Status being transitioned to must be a valid `PlayoutState` class");
        // }

        Log::debug('-----> DISPATCHING statuses to Schedule Set: "'.Str::slug($this->scheduleSet->name).'" -- '.json_encode($data), [
            'connection' => $this->connection,
            'schedule_set_id' => $this->scheduleSet->id,
            'data' => json_encode($data),
        ]);
        // ray('-----> DISPATCHING statuses to Schedule Set: "'.Str::slug($this->scheduleSet->name).'" -- '.json_encode($data))->purple();
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // broadcast status change to porta front end
        // -- status doesn't need to worry about correct channels per page as long as it gets to porta's selected schedule set;
        // -- the engine(s) get a separate command to play on the correct channel
        ScheduleUIStatusChanged::dispatch($this->scheduleSet, $this->data);
    }
}
