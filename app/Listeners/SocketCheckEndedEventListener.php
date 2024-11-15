<?php

namespace App\Listeners;

use App\Exceptions\SocketConnectionException;
use App\Health\SocketConnectionCheck;
use App\Services\Monitoring\SocketConnectionService;
use Illuminate\Support\Facades\Log;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Events\CheckEndedEvent;

/**
 * After a socket connection check, if the check failed, attempt to recover
 * the connection and set it as the default connection.
 *
 * ?? -- needs clarification --> NOTE: CheckEndedEvent is only triggered from the package's
 * default Artisan check command, not anytime a check is done within the application
 */
class SocketCheckEndedEventListener extends SingleCheckEndedEventListener
{
    // base name of the health check class
    protected string     $checkClassBaseName;
    protected Result     $result;
    protected Check      $check;
    protected string|int $status;

    public function __construct(protected SocketConnectionService $connService) {}

    /**
     * @throws \Exception
     */
    public function handle(CheckEndedEvent $event) : void
    {
        if($this->handleEvent($event, SocketConnectionCheck::class)) {
            // Doesn't need further handling
            return;
        }

        // socket connection failed
        $failedConnection = $this->result->meta['connection_name'] ?? null;

        try {
            Log::warning(" !! Attempting to recover socket connection... ");
            $this->connService->recoverConnection($failedConnection);
        } catch(\Exception $e) {
            Log::error(" !! SOCKET CONNECTION '$failedConnection' FAILED the '{$this->checkClassBaseName}' check, and recovery has failed");
            throw new SocketConnectionException("Could not recover a socket connection ({$e->getMessage()})", 0404, $e);
        }

    }
}
