<?php

namespace App\Events;

/**
 * Broadcast the Scheduler's playout data to the engine
 */
class SchedulerPlayout extends Playout
{
    /**
     * The name of the queue connection to use when broadcasting the event.
     *
     * @var string
     */
    public $connection = 'sync';
}
