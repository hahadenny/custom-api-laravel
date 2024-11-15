<?php

namespace App\Listeners;

use App\Traits\CachesCheckResults;
use Spatie\Health\Enums\Status;
use Spatie\Health\Events\CheckEndedEvent;

class CheckEndedEventListener
{
    use CachesCheckResults;

    public function __construct() {}

    public function handle(CheckEndedEvent $event) : void
    {
        if(!isset($event->check)){
            // check was not run successfully
            // usually this occurs when the server worker is running these commands before the application is ready
            return;
        }

        if(method_exists($event->check, 'getCacheKey')){
            $cacheKey = $event->check->getCacheKey();
        } else {
            $cacheKey = $event->check->getName();
        }

        $this->cacheCheckStartNowIfEmpty($cacheKey);

        if($event->result->status->value === Status::ok()->value){
            $this->cacheCheckSuccessNow($cacheKey);
            return;
        }

        if($event->result->status->value !== Status::skipped()->value){
            $this->cacheCheckFailureNow($cacheKey);
            return;
        }
    }
}
