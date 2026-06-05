<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewPostNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Post        $post,
        public User|Admin  $actor
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'        => 'منشور جديد',
            'message'      => "نشر {$this->actor->name} منشوراً جديداً منذ {$this->post->published_at->diffForHumans()}",
            'type'         => 'new_post',
            'reference_id' => $this->post->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title'        => 'منشور جديد',
            'message'      => "نشر {$this->actor->name} منشوراً جديداً منذ {$this->post->published_at->diffForHumans()}",
            'type'         => 'new_post',
            'reference_id' => $this->post->id,
        ]);
    }
}
