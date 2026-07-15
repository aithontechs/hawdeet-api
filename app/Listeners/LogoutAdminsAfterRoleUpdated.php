<?php

namespace App\Listeners;

use App\Events\Auth\RoleUpdated;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogoutAdminsAfterRoleUpdated
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    public function handle(RoleUpdated $event): void
    {
        $event->role->admins()->update([
            'tokens_invalidated_at' => Carbon::now(),
        ]);
    }
}
