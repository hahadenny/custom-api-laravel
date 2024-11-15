<?php

namespace App\Services\Engines;

use Illuminate\Support\Str;

/**
 * Template for behavior from
 * UnrealEngine.js > UEEvents()
 * D3.js > D3Events()
 */
abstract class EngineEvent
{
    public function __construct(
        protected string              $namespace,
        protected int                 $nextId = 0,
        protected array               $requests = [],
        protected ?string             $url = null,
        protected ?EngineSchemaHelper $schemaHelper = null,
        protected ?EngineHelper       $helper = null,
    )
    {
        $this->schemaHelper ??= new EngineSchemaHelper();
        $this->helper ??= new EngineHelper();
    }

    abstract public function buildSubmission($channel, $data, $schema, ...$params);

    // split from UnrealEngine.js > UEEvents > SendRequest()
    public function prepareRequest($channel, $url, $verb, $body, $parallel = false) : array
    {
        // Log::debug('-- preparing request -- ');
        // Log::debug('request body: '.print_r($body, true));
        // Log::debug('JSON body: '.json_encode($body, JSON_UNESCAPED_SLASHES));

        $this->nextId++;
        $message_id = $this->nextId;
        $this->requests[$message_id] = ''; //resolve;

        $payload = ['url' => $url, 'verb' => $verb, 'message_id' => "$message_id"];
        if ( !empty($body)) {
            $payload['content'] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        if ($parallel) {
            $payload['parallel'] = $parallel;
        }

        // socket server needs prepended '/'
        $namespace = Str::startsWith($this->namespace, '/') ? $this->namespace : '/' . $this->namespace;

        return ['namespace' => $namespace, 'channel' => $channel, 'message' => $payload];
    }

    public function convertUrlPaths(array|string $url) : array|string
    {
        return $this->helper->convertUrlPaths($url);
    }
}
