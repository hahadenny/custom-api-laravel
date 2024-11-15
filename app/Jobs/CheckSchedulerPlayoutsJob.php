<?php

namespace App\Jobs;

use App\Events\PlayoutFinished;
use App\Models\Schedule\ScheduleChannelPlayout;
use App\Models\Schedule\States\Finished;
use App\Models\Schedule\States\Next;
use App\Models\Schedule\States\Playing;
use App\Services\Schedule\Helpers\LoopModeService;
use App\Services\Schedule\Helpers\PlayoutProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckSchedulerPlayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $playingNow = [];
    public array $playingNext = [];

    public function __construct()
    {
        /**
         * The name of the queue connection the job should be sent to.
         */
        $this->connection = 'sync';
    }

    /**
     * Execute the job
     *
     * @throws \Exception
     */
    public function handle(LoopModeService $loopService, PlayoutProcessor $processor)
    {
        try {
            // look for "playing" playouts for all ScheduleSets
            $playouts = ScheduleChannelPlayout::whereAnySetHasSameStatus(Playing::class)->get();
        } catch (\Exception $e) {
            Log::error("!!Error in CheckSchedulerPlayoutsJob when checking for playouts: " . $e->getMessage());
            return;
        }

        if ($playouts->count() === 0) {
            // none are playing --> nothing to do
            return;
        }

        DB::transaction(function () use ($loopService, $playouts, $processor) {
            // send together
            $this->playingNow = [];
            $this->playingNext = [];

            $playouts->each(function ($oldPlayout) use (&$loopService, $processor) {

                $this->playingNow [$oldPlayout->schedule_set_id][$oldPlayout->id] = [
                    'page' => $oldPlayout->listing->scheduleable,
                    'playout' => $oldPlayout
                ];

                /** @var ScheduleChannelPlayout $oldPlayout */
                if($oldPlayout->end <= now()) {
                    // set playing to finished
                    $oldPlayout->status->transitionTo(Finished::class);
                    unset($this->playingNow [$oldPlayout->schedule_set_id][$oldPlayout->id]);

                    $playout = $loopService->nextValidPlayoutFromExisting($oldPlayout, now());

                    // set old "nextPlayout" to playing
                    $playout->status->transitionTo(Playing::class);
                    $listing = $playout->listing;
                    $listing->loadMissing('scheduleable', 'schedule.rules');
                    /** @var \App\Models\Page $page */
                    $page = $listing->scheduleable;

                    // send new "playing" playout
                    // todo: (after commit? - might be too delayed?)
                    SendSchedulerPlayoutJob::dispatch($page, $playout->playoutChannel)->afterCommit();

                    // determine "nextPlayout" for $playout for the selected ScheduleSet listing
                    //  and add to $this->playingNext
                    $next_playable_node = $loopService->findNextValidPlayableNode($listing, $playout->end) ?? $listing;
                    [$nextPlayout, $this->playingNow, $this->playingNext] = $processor->createNewPlayout(
                        $playout->scheduleSet,
                        null,
                        $next_playable_node,
                        $playout->end,
                        Next::$name,
                        $this->playingNow,
                        $this->playingNext
                    );

                    $playout->next()->associate($nextPlayout);
                    $playout->save();
                    // fire event for internal use (tracking playout history)
                    PlayoutFinished::dispatch($oldPlayout);

                    // ready "playing" status for UI
                    $this->playingNow [$playout->schedule_set_id][$playout->id] = ['page' => $page, 'playout' => $playout];
                } elseif(!empty($this->playingNow)) {
                    // since we're sending everything together, if there are differing durations,
                    // we still want to indicate in UI that those are playing,
                    // not just ones that may have changed.
                    // But we only want to do this if something has already changed
                    $this->playingNow [$oldPlayout->schedule_set_id][$oldPlayout->id] = [
                        'page'    => $oldPlayout->listing->loadMissing('schedule')->scheduleable,
                        'playout' => $oldPlayout
                    ];
                    $oldNextPlayout = $oldPlayout->next;
                    $this->playingNext [$oldNextPlayout->schedule_set_id][$oldNextPlayout->id] = [
                        'page'    => $oldNextPlayout->listing->loadMissing('schedule')->scheduleable,
                        'playout' => $oldNextPlayout
                    ];
                }
            }); // end each playouts

            if(!empty($this->playingNow) || !empty($this->playingNext)) {
                $loopService->updateUiStatus($this->playingNow, $this->playingNext);
            }
        }); // end transaction
    }
}
