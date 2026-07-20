<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ShippingService
{
    const CACHE_KEY = 'shipping_zones';
    const CACHE_TTL = 60 * 24;

    public function getZones()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return ShippingZone::where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'cost' , 'cost_usd', 'days_min', 'days_max']);
        });
    }

    public function getZone(int $zoneId)
    {
        return $this->getZones()->firstWhere('id', $zoneId);
    }

    public function getDefaultZone()
    {
        return $this->getZones()->firstWhere('is_default', true);
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
    }
}
