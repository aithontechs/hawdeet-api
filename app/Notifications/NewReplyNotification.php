<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class NewReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Comment     $comment,
        public User|Admin  $actor
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'        => 'رد جديد',
            'message'      => "رد {$this->actor->name} على تعليقك",
            'type'         => 'new_reply',
            'reference_id' => $this->comment->post_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
