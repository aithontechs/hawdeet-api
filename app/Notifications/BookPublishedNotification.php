<?php

namespace App\Notifications;

use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class BookPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Book $book) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'        => 'كتاب جديد متاح الآن!',
            'message'      => "تم نشر كتاب \"{$this->book->title}\" وأصبح متاحاً الآن",
            'type'         => 'book_published',
            'reference_id' => $this->book->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title'        => 'كتاب جديد متاح الآن!',
            'message'      => "تم نشر كتاب \"{$this->book->title}\" وأصبح متاحاً الآن",
            'type'         => 'book_published',
            'reference_id' => $this->book->id,
        ]);
    }
}
