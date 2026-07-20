<?php

namespace App\Services\Currency;

use Illuminate\Http\Request;

class CurrencyResolver
{
    public const SUPPORTED = ['EGP', 'USD'];

    public function resolve(Request $request): string
    {
        $header = strtoupper((string) $request->header('X-Currency'));
        if (in_array($header, self::SUPPORTED, true)) {
            return $header;
        }

        $user = auth('user-api')->user();
        if ($user?->preferred_currency && in_array($user->preferred_currency, self::SUPPORTED, true)) {
            return $user->preferred_currency;
        }

        return 'EGP';
    }
}
