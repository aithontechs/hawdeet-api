<?php

namespace App\Http\Controllers\Dashboard\Notification;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminBroadcastNotification;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdminNotificationController extends Controller
{
    use ResponseApi;

    public function broadcast(Request $request)
    {
        $request->validate([
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $count = 0;
        User::where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->chunk(2, function ($users) use ($request, &$count) {

                Notification::send($users, new AdminBroadcastNotification(
                    title: $request->title,
                    message: $request->message,
                ));

                $count += $users->count();
            });

        return $this->successApi(
            data: ['recipients_count' => $count],
            message: 'Notification sent to all users successfully'
        );
    }
}
