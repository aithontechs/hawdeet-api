<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    public function usdToEgpRate(): float
    {
        return Cache::remember('exchange_rate:usd_egp', now()->addHours(6), function () {
            try {
                $response = Http::timeout(5)->get('https://api.exchangerate-api.com/v4/latest/USD');
                $rate = $response->json('rates.EGP');
                if ($rate) return (float) $rate;
            } catch (\Throwable $e) {
                Log::error('Exchange rate fetch failed: ' . $e->getMessage());
            }
            return (float) config('paymob.fallback_usd_egp_rate', 49);
        });
    }
}
