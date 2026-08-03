<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use App\Models\User;

class LocationVerificationService
{
    private const EGYPT_PHONE_PREFIXES = ['+20', '0020', '01'];

    public function isInsideEgypt(User $user, ?ShippingZone $shippingZone = null): bool
    {
        if ($shippingZone) {
            return $this->isEgyptCountry($shippingZone->country);
        }

        $defaultZone = $user->shippingAddresses()
            ->with('zone')
            ->where('is_default', true)
            ->first()?->zone;

        if ($defaultZone) {
            return $this->isEgyptCountry($defaultZone->country);
        }

        return $this->isEgyptianPhone($user->phone)
            || $this->isEgyptianCurrency($user->preferred_currency ?? null);
    }

    public function isEgyptCountry(?string $country): bool
    {
        return strtoupper(trim((string) $country)) === 'EG';
    }

    public function isEgyptianPhone(?string $phone): bool
    {
        if (! $phone) {
            return false;
        }

        $phone = preg_replace('/\s+/', '', $phone);

        foreach (self::EGYPT_PHONE_PREFIXES as $prefix) {
            if (str_starts_with($phone, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isEgyptianCurrency(?string $currency): bool
    {
        return $currency === 'EGP';
    }
}
