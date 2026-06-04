<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $message, public string $type, public int $referenceId)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'عملية دفع ناجحه',
            'message' => $this->message,
            'type' => $this->type,
            'reference_id' => $this->referenceId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'عملية دفع ناجحه',
            'message'      => $this->message,
            'type'         => $this->type,
            'reference_id' => $this->referenceId,
            'read_at'      => null,
        ]);
    }

    public function broadcastType(): string
    {
        return 'payment.success';
    }

}
