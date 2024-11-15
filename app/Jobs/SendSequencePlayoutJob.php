<?php

namespace App\Jobs;

use App\Events\SequencePlayout;
use App\Models\Channel as PortaChannel;
use App\Models\Page;
use App\Services\Schedule\Helpers\PlayoutProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSequencePlayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Page $page, protected PortaChannel $channel)
    {
        Log::debug("in Job constructor for $page->name, $channel->name");
    }

    /**
     * Execute the job.
     */
    public function handle(PlayoutProcessor $processor)
    {
        Log::debug("dispatching '{$this->page->name}' to SequencePlayout, channel: {$this->channel->name}");

        // ray("dispatching '{$this->page->name}' to SequencePlayout, channel: {$this->channel->name}")->orange();

        $data = $processor->prepareBroadcastData($this->page, $this->channel);

        // ray($data)->orange()->label("'{$this->page->name}' DATA");

        if(isset($data)){
            // broadcast the data
            SequencePlayout::dispatch($this->channel, $data);
        }
    }
}
