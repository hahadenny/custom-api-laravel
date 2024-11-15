<?php

namespace App\Services\Engines\Unreal;

use App\Services\Engines\EngineBatch;
use App\Services\Engines\EngineRequest;

class UnrealBatch extends EngineBatch
{
    public function addRequest(string $url, string $verb, string|array $content) : void
    {
        $this->message->addRequest((new UnrealRequest($this->curr_id++, $url, $verb, $content)));
    }

    // split from UnrealEngine.js > UEBatch > send()
    public function prepareToSend(string $channel) : array
    {
        // Log::debug("Batch's message: ".print_r($this->message, true));

        switch(sizeof($this->message->requests)) {
            case 0:
                return [];
            case 1:
                /** @var EngineRequest<UnrealRequest> $request */
                $request = $this->message->requests[0];
                return $this->eventService->prepareRequest($channel, $request->url, $request->verb, $request->content);
            default:
                return $this->eventService->prepareRequest($channel, "/remote/batch", "PUT", $this->message);
        }
    }
}
