<?php

namespace App\Events;

use App\Models\Cluster;
use App\Models\Company;
use App\Models\SocketServer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TerraformSocketRunCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(public int $run_id, public SocketServer $server)
    {
    }

}
