<?php

namespace App\Services\Engines\D3;

use App\Services\Engines\EngineRequest;

class D3Request extends EngineRequest
{
    /**
     * Customize the structure when JSON encoding
     */
    public function jsonSerialize() : array
    {
        /** props need this index casing for an unreal/avalanche request */
        return [
            "request_id" => $this->request_id,
            "url"       => $this->url,
            "verb"      => $this->verb,
            "body"      => $this->content,
        ];
    }
}
