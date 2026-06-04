<?php

namespace App\Http\Controllers\Application\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ResponseApi;

    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(30);
        $notifications->setCollection(NotificationResource::collection($notifications->getCollection())->collection);
        return $this->successApi($notifications,'Notifications fetched successfully');
    }

    public function unread(Request $request)
    {
        $notifications = $request->user()->unreadNotifications()->paginate(20);
        $notifications->setCollection(NotificationResource::collection($notifications->getCollection())->collection);
        return $this->successApi($notifications,'Unread notifications fetched successfully');
    }

    public function unreadCount(Request $request)
    {
        $count = $request->user()->unreadNotifications()->count();
        return $this->successApi(['count' => $count], 'Unread count fetched successfully');
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }
        if($notification->read_at){
            return $this->errorApi('Notification already read', 400);
        }
        $notification->markAsRead();

        return $this->successApi(new NotificationResource($notification) , message: 'Notification marked as read');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return $this->successApi(message :'All notifications marked as read');
    }

    public function destroy(Request $request, string $id)
    {
        $deleted = $request->user()->notifications()->where('id', $id)->delete();

        if (!$deleted) {
            return $this->errorApi('Notification not found', 404);
        }

        return $this->successApi(null ,'Notification deleted successfully');
    }
}
