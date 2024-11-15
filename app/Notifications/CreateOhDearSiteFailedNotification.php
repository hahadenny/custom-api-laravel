<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\SocketServer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreateOhDearSiteFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $error_message, public SocketServer $server, public ?string $response = null)
    {
    }

    public function via($notifiable) : array
    {
        return ['mail'];
    }

    public function toMail($notifiable) : MailMessage
    {
        $mailMessage = (new MailMessage)
            ->error()
            ->subject('OhDear Site Creation Failure - '.$this->error_message)
            ->line('Failed for: "' . $this->server->url . '" in region: ' . $this->server?->cluster->region);

        return isset($this->response) ? $mailMessage->line("`Response` received: \n " . $this->response) : $mailMessage;
    }
}
