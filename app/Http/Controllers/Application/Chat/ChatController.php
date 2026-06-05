<?php
namespace App\Http\Controllers\Application\Chat;

use App\Events\Chat\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    use ResponseApi;

    public function index(Request $request)
    {
        $messages = ChatMessage::where('user_id', $request->user()->id)->orderBy('created_at', 'desc')->paginate(50);
        $messages->setCollection(ChatMessageResource::collection($messages->getCollection())->collection);
        return $this->successApi($messages , 'Messages fetched successfully');
    }

    public function store(Request $request)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $chatMessage = ChatMessage::create([
            'user_id'     => $request->user()->id,
            'sender_type' => 'user',
            'message'     => $request->message,
        ]);

        event(new MessageSent($chatMessage));
        return $this->successApi(new ChatMessageResource($chatMessage), 'Message sent successfully');
    }

    public function markAsRead(Request $request)
    {
        ChatMessage::where('user_id', $request->user()->id)->where('sender_type', 'admin')->whereNull('read_at')->update(['read_at' => now()]);
        return $this->successApi(message: 'Messages marked as read');
    }
}
