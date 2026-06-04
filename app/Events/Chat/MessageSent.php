<?php

namespace App\Events\Chat;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public function __construct(public ChatMessage $message) {}

    public function broadcastOn()
    {
        return new PrivateChannel('chat.' . $this->message->user_id);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->message->id,
            'message'     => $this->message->message,
            'sender_type' => $this->message->sender_type,
            'read_at'     => $this->message->read_at,
            'created_at'  => $this->message->created_at->toISOString(),
        ];
    }
}
