<?php

namespace App\Services\Notification;

use Illuminate\Http\Request;

class NotificationFilterService
{
    public function apply($query, Request $request)
    {
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->search;

            $q->where(function ($q) use ($search) {
                $q->where('data->title', 'like', "%{$search}%")
                    ->orWhere('data->message', 'like', "%{$search}%");
            });
        });

        if ($request->filled('status')) {
            match ($request->status) {
                'read'   => $query->whereNotNull('read_at'),
                'unread' => $query->whereNull('read_at'),
                default  => null,
            };
        }

        return $query;
    }
}