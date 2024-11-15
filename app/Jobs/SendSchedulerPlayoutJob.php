<?php

namespace App\Jobs;

use App\Events\SchedulerPlayout;
use App\Models\Channel as PortaChannel;
use App\Models\Page;
use App\Services\Schedule\Helpers\PlayoutProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSchedulerPlayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Page $page, protected PortaChannel $channel)
    {
        /**
         * The name of the queue connection the job should be sent to.
         */
        $this->connection = 'sync';
    }

    /**
     * Execute the job.
     */
    public function handle(PlayoutProcessor $processor)
    {
        Log::debug("dispatching '{$this->page->name}' to SchedulerPlayout, channel: {$this->channel->name}");

        $data = $processor->prepareBroadcastData($this->page, $this->channel);
        Log::debug("==============================================");
        Log::debug("broadcast data :");
        Log::debug(print_r($data, true));
        Log::debug("==============================================");

        if(isset($data)){
            // broadcast the data
            SchedulerPlayout::dispatch($this->channel, $data);
        }
    }
}
