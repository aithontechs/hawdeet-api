<?php

namespace App\Services\Maintenance;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\UserSubscription;
use App\Services\DashboardStats\DashboardService;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    public function __construct(private readonly DashboardService $dashboardService){}

    public function expireSubscriptions(): void
    {
        UserSubscription::query()
            ->where('status', 'active')
            ->where('end_at', '<=', now())
            ->update([
                'status' => 'expired',
                'ended_reason' => 'expired',
            ]);
    }

    public function expireCoupons(): void
    {
        Coupon::query()
            ->where('status', 'active')
            ->whereNotNull('end_at')
            ->where('end_at', '<=', now())
            ->update([
                'status' => 'inactive',
            ]);
    }

    public function deletePendingSubscriptions(): void
    {
        DB::transaction(function () {

            $ids = UserSubscription::query()
                ->where('status', 'inactive')
                ->whereIn('payment_status', ['pending' , 'failed'])
                ->where('created_at', '<=', now()->subMinutes(30))
                ->pluck('id');

            if ($ids->isEmpty()) {
                return;
            }

            Payment::whereIn('user_subscription_id', $ids)
                        ->whereIn('status', ['pending' , 'failed'])
                        ->delete();

            UserSubscription::whereIn('id', $ids)->delete();
            $this->dashboardService->clearCache() ;
        });
    }
}
