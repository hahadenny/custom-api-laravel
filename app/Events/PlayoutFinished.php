<?php

namespace App\Events;

use App\Models\Schedule\ScheduleChannelPlayout;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Playout is done playing and has transitioned to "finished" status
 */
class PlayoutFinished
{
    use Dispatchable, SerializesModels;

    /**
     * The name of the queue connection to use when broadcasting the event.
     *
     * @var string
     */
    public $connection = 'sync';

    public function __construct(public ScheduleChannelPlayout $playout)
    {
    }
}
