<?php

namespace App\Services\Auth;

use Propaganistas\LaravelPhone\PhoneNumber;

class PhoneNormalizer
{
    public function normalize(?string $rawPhone): ?string
    {
        if (!$rawPhone) {
            return null;
        }

        $trimmed = preg_replace('/[\s\-\(\)]/', '', trim($rawPhone));

        if (str_starts_with($trimmed, '+') || str_starts_with($trimmed, '00')) {
            try {
                return (new PhoneNumber($trimmed))->formatE164();
            } catch (\Throwable $e) {
                return $trimmed;
            }
        }

        try {
            return (new PhoneNumber($trimmed, 'EG'))->formatE164();
        } catch (\Throwable $e) {
            return $trimmed;
        }
    }
}
