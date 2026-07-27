<?php

namespace App\Http\Controllers\Dashboard\DashboardStats;

use App\Http\Controllers\Controller;
use App\Services\DashboardStats\DashboardService;
use App\Traits\ResponseApi;

class DashboardController extends Controller
{
    use ResponseApi;

    public function __construct(private readonly DashboardService $dashboardService) {}

    public function stats()
    {
        $stats = $this->dashboardService->getStats();
        return $this->successApi($stats, 'Dashboard stats fetched successfully');
    }
}
