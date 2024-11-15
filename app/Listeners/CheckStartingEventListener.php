<?php

namespace App\Listeners;

use App\Traits\CachesCheckResults;
use Spatie\Health\Events\CheckStartingEvent;

class CheckStartingEventListener
{
    use CachesCheckResults;

    public function __construct() {}

    public function handle(CheckStartingEvent $event) : void
    {
        if(!isset($event->check)){
            // check was not started successfully
            // usually this occurs when the server worker is running these commands before the application is ready
            return;
        }

        if(method_exists($event->check, 'getCacheKey')){
            $cacheKey = $event->check->getCacheKey();
        } else {
            $cacheKey = $event->check->getName();
        }

        $this->cacheCheckStartNowIfEmpty($cacheKey);
    }
}
