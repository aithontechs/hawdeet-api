<?php

namespace App\Console;

use App\Models\Coupon;
use App\Models\UserSubscription;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            UserSubscription::query()
                ->where('status', 'active')
                ->where('end_at', '<=', now())
                ->update([
                    'status' => 'expired',
                    'ended_reason' => 'expired',
                ]);
        })->everyMinute();

        $schedule->call(function () {
            Coupon::query()
                ->where('status', 'active')
                ->whereNotNull('end_at')
                ->where('end_at', '<=', now())
                ->update([
                    'status' => 'inactive',
                ]);
        })->everyMinute();
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
