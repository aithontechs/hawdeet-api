<?php

namespace App\Console;

use App\Services\Maintenance\MaintenanceService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $service = app(MaintenanceService::class);

        $schedule->call(fn () => $service->expireSubscriptions())->hourly() ;
        $schedule->call(fn () => $service->expireCoupons())->everyMinute();
        $schedule->call(fn () => $service->deletePendingSubscriptions())->everyFiveMinutes();
    }


    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
