<?php

namespace App\Services\Engines;

/**
 * Instances of EngineRequest are used to populate EngineMessage::$requests
 */
abstract class EngineRequest implements \JsonSerializable
{
    public function __construct(
        public int               $request_id,
        public string|array      $url,
        public string            $verb,
        public string|array|null $content=null,
    )
    {
    }

    public function toJson() : array
    {
        return $this->jsonSerialize();
    }

    public function jsonSerialize() : array
    {
        /** props need this index casing for an unreal/avalanche request */
        return [
            "RequestId" => $this->request_id,
            "URL"       => $this->url,
            "Verb"      => $this->verb,
            "Body"      => $this->content,
        ];
    }
}
