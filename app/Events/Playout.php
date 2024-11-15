<?php

namespace App\Events;

use App\Models\Channel as PortaChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast the playout data to the engine
 */
abstract class Playout implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public PortaChannel $channel, protected $data)
    {
    }

    public function broadcastOn()
    {
        // add a suffix so that the socket server can PSUBSCRIBE to the right pattern
        return new Channel($this->channel->name.'-API-msg');
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message';
    }
    //*/

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return $this->data;
    }
}
