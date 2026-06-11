<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $redirectUrl = config('app.frontend_url') . '/reset-password?token=' . $this->token . '&email=' . $notifiable->email ;
        // https://blog.3azmagroup.cloud/reset-password
        return (new MailMessage)
            ->subject('Reset Password')
            ->line('Click below to reset your password')
            ->action('Reset Password', $redirectUrl) ;
    }
}
