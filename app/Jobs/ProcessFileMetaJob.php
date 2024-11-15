<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FileMetaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFileMetaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $authUser, public array $params=[], public string $method='POST') {}

    /**
     * @throws \JsonException
     */
    public function handle(FileMetaService $service) : void {
        if(strtoupper($this->method) === 'DELETE') {
            $service->bridgeDelete($this->authUser, $this->params);
            return;
        }
        $service->upsert($this->authUser, $this->params);
    }
}
