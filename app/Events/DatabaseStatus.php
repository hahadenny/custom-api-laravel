<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast status change to scheduler UI
 */
class DatabaseStatus implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * The name of the queue connection to use when broadcasting the event.
     *
     * @var string
     */
    public $connection = 'sync';

    public function __construct(public $dbEventData) {}

    public function broadcastOn()
    {
        // the Redis channel
        // -- add a suffix so that the socket server can PSUBSCRIBE to the right pattern
        return new Channel('databaseStatus-API-msg');
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'databaseStatus';
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
            'channel'      => 'databaseStatus',
            'data'         => $this->dbEventData,
        ];
    }
}
