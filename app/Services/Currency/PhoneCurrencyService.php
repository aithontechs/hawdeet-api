<?php

namespace App\Services\Currency ;

class PhoneCurrencyService
{
    public function resolveFromPhone(?string $phone)
    {
        if (!$phone) {
            return null;
        }

        $normalized = $this->normalize($phone);

        if ($this->isEgyptianNumber($normalized)) {
            return 'EGP';
        }

        if ($this->hasExplicitCountryCode($normalized) && !$this->isEgyptianNumber($normalized)) {
            return 'USD';
        }

        if (preg_match('/^01[0-9]{9}$/', $normalized)) {
            return 'EGP';
        }
        return null;

    }
    private function normalize(string $phone): string
    {
        return preg_replace('/[\s\-\(\)]/', '', trim($phone));
    }

    private function isEgyptianNumber(string $phone): bool
    {
        return str_starts_with($phone, '+20');
    }

    private function hasExplicitCountryCode(string $phone): bool
    {
        return str_starts_with($phone, '+') || str_starts_with($phone, '00');
    }

    // في PhoneCurrencyService - إضافة method مخصصة للتسجيل بترجع قيمة أكيدة دايماً
    public function resolveFromPhoneOrDefault(?string $phone, string $default = 'EGP'): string
    {
        return $this->resolveFromPhone($phone) ?? $default;
    }

}
