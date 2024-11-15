<?php

namespace App\Services\Engines;

/**
 * A JsonSerializable collection of EngineRequests
 */
abstract class EngineMessage implements \JsonSerializable
{
    public function __construct(
        /** @var EngineRequest[] $requests */
        protected array $requests = [],
    )
    {
    }

    public function __get(string $name)
    {
        return $this->$name;
    }

    public function addRequest(EngineRequest $request) : void
    {
        $this->requests []= $request;
    }

    /**
     * Customize the structure when JSON encoding
     */
    public function jsonSerialize() : array
    {
        /** unreal request must be padded with "Requests:" */
        return ["Requests" => $this->requests];
    }
}
