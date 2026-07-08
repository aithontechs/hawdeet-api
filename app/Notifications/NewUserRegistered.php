<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\User;

class NewUserRegistered extends Notification implements ShouldBroadcast
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
            'title' => 'مستخدم جديد',
            'message' => "{$this->user->name}  انضم الي المنصة",
            'type' => 'new_user_registered',
            'reference_id' => $this->user->id,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'مستخدم جديد',
            'message' => "{$this->user->name}  انضم الي المنصة",
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function broadcastType(): string
    {
        return 'new-user-registered';
    }
}
