<?php

namespace App\Http\Controllers\Application\BroadCast;

use App\Http\Controllers\Controller;
use App\Services\Pusher\PusherService;
use Illuminate\Http\Request;

class BroadCastController extends Controller
{
    public function auth(Request $request, PusherService $pusher)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $channelName = $request->input('channel_name');
        $socketId    = $request->input('socket_id');

        $expectedChannel = 'private-App.Models.User.' . $user->id;
        if ($channelName !== $expectedChannel) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response($pusher->authorizeChannel($channelName, $socketId))
            ->header('Content-Type', 'application/json');
    }
}
