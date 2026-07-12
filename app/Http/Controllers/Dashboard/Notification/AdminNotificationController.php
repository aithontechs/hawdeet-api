<?php

namespace App\Http\Controllers\Dashboard\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Notifications\AdminBroadcastNotification;
use App\Services\Notification\NotificationFilterService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdminNotificationController extends Controller
{
    use ResponseApi;

    public function getNotifications(Request $request, NotificationFilterService $filterService)
    {
        $user = $request->user();

        $query = $filterService->apply($user->notifications()->latest(), $request);
        $notifications = $query->paginate(10);

        $stats = $user->notifications()->reorder()->selectRaw("
                        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS sent_today,
                        COUNT(CASE WHEN read_at IS NULL THEN 1 END) AS unread
                    ")->first();

        return $this->successApi([
            'notifications' => NotificationResource::collection($notifications),
            'stats' => [
                'unread'         => $stats->unread ?? 0,
                'sent_today'     => $stats->sent_today ?? 0,
                'failed_to_send' => 0,
            ],
        ]);
    }

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

    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }
        return $this->successApi(new NotificationResource($notification),'Notification marked as read');
    }

    public function markAsReadAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return $this->successApi(message: 'All notifications marked as read');
    }

    public function destroy(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();
        return $this->successApi(message: 'Notification deleted successfully');
    }

    public function destroyAll(Request $request)
    {
        $request->user()->notifications()->delete();
        return $this->successApi(message: 'All notifications deleted successfully');
    }
}
