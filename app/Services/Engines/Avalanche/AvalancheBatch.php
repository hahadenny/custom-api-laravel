<?php

namespace App\Services\Engines\Avalanche;

use App\Services\Engines\EngineBatch;
use App\Services\Engines\EngineEvent;
use App\Services\Engines\EngineHelper;
use App\Services\Engines\EngineMessage;
use App\Services\Engines\EngineRequest;
use App\Services\Engines\Unreal\UnrealMessage;
use Illuminate\Support\Facades\Log;

class AvalancheBatch extends EngineBatch
{
    public EngineEvent $eventService;
    public EngineMessage $message;
    public $asset;
    public $avalancheChannel;
    protected int $curr_id = 0;
    protected EngineHelper $helper;

    public function __construct(EngineEvent $eventService, $asset, $avalancheChannel, EngineMessage $message = null, EngineHelper $helper = null)
    {
        $this->eventService = $eventService;
        $this->message = $message ?? new UnrealMessage();
        $this->helper ??= new EngineHelper();
    }

    /**
     * UnrealEngine.js > AvalancheBatch > request()
     */
    public function addRequest(string $url, string $verb, string|array $content) : void
    {
        $this->message->addRequest((new AvalancheRequest($this->curr_id++, $url, $verb, $content)));
    }

    /**
     * split from UnrealEngine.js > AvalancheBatch > send()
     */
    public function prepareToSend(string $channel) : array
    {
        $assetMatch = $this->helper->findAssetMatch($this->asset);

        // NOTE: this $request seems to be here in JS as a quick fix for now,
        //       so the rest of this function is proceeding as normal
        // request body
        $request = [
            "objectPath" => "/Script/PortaInterface.Default__PortaAvalancheSubsystem",
            "functionName" => "StartAvalancheStatic",
            "Async" => true,
            "parameters" => [
                "Path" => $this->asset . "." . substr($assetMatch, 1),
                "Channel" => $this->avalancheChannel,
                // will be padded with ["Requests" => ...]
                "BatchRequest" => json_encode($this->message)
            ]
        ];
        $this->addRequest("/remote/object/call", "PUT", $request);
        // END of noted behavior

        Log::debug("Avalanche Submission --> channel: ");
        Log::debug($channel);
        Log::debug("request: ");
        Log::debug($request);
        Log::debug("Batch's message: ".print_r($this->message, true));

        switch(sizeof($this->message->requests)) {
            case 0:
                return [];
            case 1:
                /** @var EngineRequest<AvalancheRequest> $request */
                $request = $this->message->requests[0];
                return $this->eventService->prepareRequest($channel, $request->url, $request->verb, $request->content);
            default:
                return $this->eventService->prepareRequest($channel, "/remote/batch", "PUT", $this->message);
        }
    }
}
