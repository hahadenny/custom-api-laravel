<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PasswordWasChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public string $newPassword;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, $newPassword)
    {
        $this->newPassword = $newPassword;
        $this->user = $user;
    }
}
