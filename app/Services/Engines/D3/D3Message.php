<?php

namespace App\Services\Engines\D3;

use App\Services\Engines\EngineMessage;

class D3Message extends EngineMessage
{
    /**
     * Customize the structure when JSON encoding
     */
    public function jsonSerialize() : array
    {
        return $this->requests;
    }
}
