<?php

namespace App\Services\Engines\D3;

use App\Services\Engines\EngineBatch;
use App\Services\Engines\EngineEvent;
use App\Services\Engines\EngineMessage;

class D3Batch extends EngineBatch
{
    public function __construct(EngineEvent $eventService, EngineMessage $message = null)
    {
        $message ??= new D3Message();

        /** @var D3Event $eventService */
        parent::__construct($eventService, $message);
    }

    /**
     * Append an EngineRequest
     *
     * split from UnrealEngine.js > UEBatch > request()
     *
     * @param array|string $url
     * @param string       $verb
     * @param string|array|null $content - JSON payload
     *
     * @return void
     */
    public function addRequest(array|string $url, string $verb, string|array $content=null) : void
    {
        $this->message->addRequest((new D3Request(
            $this->curr_id++,
            $this->eventService->convertUrlPaths($url),
            $verb,
            $content
        )));
    }

    // split from UnrealEngine.js > UEBatch > send()
    public function prepareToSend(string $channel) : array
    {
        // d3 (Porta Bridge) is expecting an array of arrays of urls and their associated JSON data
        /* Ex:
          [
              ["/api/session/transport/engaged", "{"transports":[{"transport":{"uid":"17073021107217723848"},"engaged":true}]}"],
              ["/api/experimental/sockpuppet/live", "{"patches":[{"address":"VideoLayer","key":"patch_videolayer_brightness","changes":[{"field":"brightness","floatValue":{"value":1}}]}]
          ]
         */
        $requestUrls = [];
        foreach($this->message->requests as $request){
            foreach($request->url as $url){
                $requestUrls []= $url;
            }
        }

        return $this->eventService->prepareRequest(
            $channel,
            $requestUrls,
            "POST",
            $this->message
        );

    }
}
