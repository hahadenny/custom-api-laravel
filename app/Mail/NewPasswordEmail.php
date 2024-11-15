<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewPasswordEmail extends Mailable
{
    use Queueable, SerializesModels;

    private User $user;
    private string $newPassword;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, $newPassword)
    {
        $this->newPassword = $newPassword;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your password was reset.')
            ->markdown('emails.users.new_password', ['user' => $this->user, 'newPassword' => $this->newPassword]);
    }
}
