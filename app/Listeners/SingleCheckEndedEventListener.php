<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;
use Spatie\Health\Events\CheckEndedEvent;

/**
 * ?? -- needs clarification --> NOTE: CheckEndedEvent is only triggered from the package's
 * default Artisan check command, not anytime a check is done within the application
 */
abstract class SingleCheckEndedEventListener
{
    // base name of the health check class
    protected string     $checkClassBaseName;
    protected Result     $result;
    protected Check      $check;
    protected string|int $status;

    abstract public function handle(CheckEndedEvent $event) : void;

    /**
     * Returns `false` if the check needs further handling
     *
     * @param CheckEndedEvent $event - The event we heard
     * @param string          $checkClassName - The name of the Check class we're looking for
     */
    public function handleEvent(CheckEndedEvent $event, string $checkClassName) : bool
    {
        if(!isset($event->check)){
            // no health checks were run successfully
            // usually this occurs when the server worker is running these commands before the application is ready
            Log::warning("No health checks were run successfully (for " . class_basename(static::class) . " -- ".now().")");
            return true;
        }

        $this->init($event);

        if($this->checkClassBaseName !== class_basename($checkClassName)) {
            return true;
        }

        if($this->status === Status::ok()->value){
            return true;
        }

        return false;
    }

    protected function wasDatabaseCheck() : bool
    {
        if(Str::contains($this->checkClassBaseName, ['Database', 'Queries', 'Query'], true)) {
            return true;
        }
        return false;
    }

    private function init(CheckEndedEvent $event) : void
    {
        $this->check = $event->check;
        $this->result = $event->result;
        $this->checkClassBaseName = class_basename($event->check::class);
        $this->status = $event->result->status->value;
    }
}
