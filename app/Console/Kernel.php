<?php

namespace App\Console;

use App\Models\UserSubscription;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $ids = UserSubscription::query()
                ->where('status', 'active')
                ->where('end_at', '<=', now())
                ->pluck('id');

            UserSubscription::findMany($ids)->each(
                fn($sub) => $sub->update([
                    'status'       => 'expired',
                    'ended_reason' => 'expired',
                ])
            );

        })->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
