<?php

namespace App\Services\Engines;

use App\Services\Engines\Unreal\UnrealMessage;

abstract class EngineBatch
{
    public EngineEvent $eventService;
    public EngineMessage $message;
    protected int $curr_id = 0;

    public function __construct(EngineEvent $eventService, EngineMessage $message = null)
    {
        $this->eventService = $eventService;
        $this->message = $message ?? new UnrealMessage();
    }

    /**
     * Append an EngineRequest
     *
     * split from UnrealEngine.js > UEBatch > request()
     *
     * @param string       $url
     * @param string       $verb
     * @param string|array $content - JSON payload
     *
     * @return void
     */
    abstract public function addRequest(string $url, string $verb, string|array $content) : void;

    // split from UnrealEngine.js > UEBatch > send()
    abstract public function prepareToSend(string $channel) : array;
}
