<?php

namespace App\Services\Pusher;

use Pusher\Pusher;

class PusherService
{
    protected Pusher $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS'  => true,
            ]
        );
    }


    public function authorizeChannel(string $channelName, string $socketId): mixed
    {
        return $this->pusher->authorizeChannel($channelName, $socketId);
    }
}
