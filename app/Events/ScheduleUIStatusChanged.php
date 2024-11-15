<?php

namespace App\Events;

use App\Models\Schedule\ScheduleSet;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast status change to scheduler UI
 */
class ScheduleUIStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * The name of the queue connection to use when broadcasting the event.
     *
     * @var string
     */
    public $connection = 'sync';

    /**
     * NOTE: Unused when implementing ShouldBroadcastNow
     *
     * The name of the queue on which to place the broadcasting job.
     *
     * @var string
     */
    // public $queue = 'schedule-status';

    public function __construct(
        public ScheduleSet         $scheduleSet,
        // array of listing ids for playout items with any status
        public array               $data
        /*[
            'playing' => [2, 10, 43],
            'next' => [3, 11, 44],
            'paused' => [2, 10, 43, 3, 11, 44],
        ]*/
    )
    {
        // if ( !class_exists($status) || !is_subclass_of($status, PlayoutState::class)) {
        //     throw new ValueError("Status being transitioned to must be a valid `PlayoutState` class");
        // }
    }

    public function broadcastOn()
    {
        // the Redis channel
        // -- add a suffix so that the socket server can PSUBSCRIBE to the right pattern
        return new Channel($this->scheduleSet->slug . '-API-msg');
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'scheduleStatus';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            // status has its own namespace; the status may apply to multiple different engines
            'namespace'    => config('broadcasting.status_settings.namespace'),
            'channel'      => $this->scheduleSet->slug,
            'playout_data' => $this->data,
        ];
    }
}
