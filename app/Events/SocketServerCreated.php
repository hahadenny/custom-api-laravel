<?php

namespace App\Events;

use App\Models\SocketServer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocketServerCreated
{
    use Dispatchable, SerializesModels;

    public SocketServer $server;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(SocketServer $server)
    {
        $this->server = $server;
    }

}
