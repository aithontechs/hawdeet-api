<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewOrderCreated extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public function __construct(protected User $user)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'طلب جديد',
            'message' => "{$this->user->name}  قام بطلب جديد",
            'type' => 'new_order_created',
            'reference_id' => $this->user->id,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title' => 'طلب جديد',
            'message' => "{$this->user->name}  قام بطلب جديد",
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function broadcastType(): string
    {
        return 'new-order-created';
    }
}