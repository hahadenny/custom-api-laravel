<?php

namespace App\Listeners;

use App\Events\PasswordWasChanged;
use App\Mail\NewPasswordEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendNewPasswordEmail
{
    /**
     * Handle the event.
     *
     * @param  PasswordWasChanged  $event
     * @return void
     */
    public function handle(PasswordWasChanged $event)
    {
        $user = $event->user;
        Mail::to($user)->send(new NewPasswordEmail($user, $event->newPassword));
    }
}
