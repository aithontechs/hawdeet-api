<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use App\Services\Currency\CurrencyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ShippingService
{
    const CACHE_KEY = 'shipping_zones';
    const CACHE_KEY_Dash = 'shipping_zones_dash';
    const CACHE_TTL = 60 * 24;

    public function __construct(private readonly CurrencyResolver $currencyResolver){}

    public function getZones(Request $request)
    {
        $currency = $this->currencyResolver->resolve($request);

        $country = $currency === 'USD' ? 'International' : 'EG';

        return Cache::remember(
            self::CACHE_KEY . '_' . $currency,
            self::CACHE_TTL,
            function () use ($currency) {
                return ShippingZone::query()
                    ->where('is_active', true)
                    ->where('country', $currency === 'USD' ? 'International' : 'EG')
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->select([
                        'id',
                        'name',
                        'days_min',
                        'days_max',
                    ])
                    ->selectRaw($currency === 'USD' ? 'cost_usd as cost' : 'cost')
                    ->get()
                    ->map(function ($zone) use ($currency) {
                        $zone->currency = $currency;

                        return $zone;
                    });
            }
        );
    }

    public function getZone(Request $request , int $zoneId)
    {
        return $this->getZones($request)->firstWhere('id', $zoneId);
    }

    public function getDefaultZone(Request $request)
    {
        return $this->getZones($request)->firstWhere('is_default', true);
    }

    public function setDefault(ShippingZone $zone)
    {
        ShippingZone::query()->update(['is_default' => false]);
        $zone->update(['is_default' => true]);
        $this->clearCache();
        return $zone;
    }

    public function find(int $id)
    {
        return ShippingZone::findOrFail($id);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {

            if (!empty($data['is_default']) && $data['is_default']) {

                ShippingZone::query()->update([
                    'is_default' => false
                ]);
            }
            $data['country'] = ! $data['country'] ? 'International' : 'EG' ;
            $zone = ShippingZone::create($data);
            $this->clearCache();
            return $zone;
        });
    }


    public function update(ShippingZone $zone, array $data)
    {
        return DB::transaction(function () use ($zone, $data) {

            if (!empty($data['is_default']) && $data['is_default']) {
                ShippingZone::query()->update([
                    'is_default' => false
                ]);
            }
            if(isset($data['country'])){
                $data['country'] = ! $data['country'] ? 'International' : 'EG' ;
            }
            $zone->update($data);
            $this->clearCache();
            return $zone;
        });
    }

    public function delete(ShippingZone $zone)
    {
        return DB::transaction(function () use ($zone) {
            if (ShippingZone::count() === 1) {
                throw new \Exception('Cannot delete the last shipping zone.');
            }
            $wasDefault = $zone->is_default;
            $zone->delete();
            if ($wasDefault) {
                $newDefaultZone = ShippingZone::query()->where('is_active', true)->latest()->first();
                if ($newDefaultZone)
                {
                    $newDefaultZone->update([
                        'is_default' => true
                    ]);
                }
            }
            $this->clearCache();
            return true;
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_Dash);

    }

    public function getZonesDashboard()
    {
        return Cache::remember(self::CACHE_KEY_Dash, self::CACHE_TTL, function () {
            return ShippingZone::orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'cost' , 'cost_usd', 'days_min', 'days_max' , 'is_active' , 'is_default']);
        });
    }

}
