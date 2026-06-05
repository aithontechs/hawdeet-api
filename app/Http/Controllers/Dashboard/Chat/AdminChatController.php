<?php

namespace App\Http\Controllers\Dashboard\Chat;

use App\Events\Chat\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\User;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class AdminChatController extends Controller
{
    use ResponseApi;

    public function conversations()
    {
        $conversations = User::query()->select('id', 'name', 'avatar_url')->whereHas('chatMessages')->with('latestChatMessage')
                                ->withCount([
                                    'chatMessages as unread_count' => function ($q) {
                                        $q->where('sender_type', 'user')
                                        ->whereNull('read_at');
                                    }
                                ])->latest()
                                ->paginate(20);

        return $this->successApi(data: $conversations,message: 'Conversations fetched successfully');
    }

    public function show(User $user)
    {
        $messages = ChatMessage::where('user_id', $user->id)->orderBy('id', 'desc')->paginate(50);

        return $this->successApi(
            data: [
                'conversation' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar_url' => $user->avatar_url,
                    ],
                ],

                'messages' => ChatMessageResource::collection($messages),
            ],
            message: 'Messages fetched successfully'
        );
    }



    public function store(Request $request, User $user)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $chatMessage = ChatMessage::create([
            'user_id'     => $user->id,
            'sender_type' => 'admin',
            'message'     => $request->message,
        ]);

        event(new MessageSent($chatMessage));

        return $this->successApi(
            data: new ChatMessageResource($chatMessage),
            message: 'Message sent successfully'
        );
    }

    public function update(Request $request, ChatMessage $message)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        if ($message->sender_type !== 'admin') {
            return $this->errorApi('You can only edit admin messages', 403);
        }

        $message->update([
            'message' => $request->message,
        ]);

        return $this->successApi(data: new ChatMessageResource($message->fresh()),message: 'Message updated successfully');
    }

    public function destroy(ChatMessage $message)
    {
        if ($message->sender_type !== 'admin') {
            return $this->errorApi('You can only delete admin messages', 403);
        }
        $message->delete();

        return $this->successApi(message: 'Message deleted successfully');
    }

    public function markAsRead(User $user)
    {
        ChatMessage::where('user_id', $user->id)
                ->where('sender_type', 'user')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

        return $this->successApi(message: 'Messages marked as read');
    }
}
