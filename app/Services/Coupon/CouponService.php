<?php

namespace App\Services\Coupon ;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class CouponService
{
    public function validate(string $code, float $orderTotal, User $user , string $currency = 'EGP')
    {
        $coupon = Coupon::where('code', $code)->where('status', 'active')->first();

        $cart = Cart::forSession(null , $user->id) ;
        if(! $cart->exists())
        {
            throw ValidationException::withMessages([
                'cart' => 'Cart is empty'
            ]);
        }

        if (!$coupon) {
            throw ValidationException::withMessages([
                'coupon' => 'Invalid or inactive coupon.',
            ]);
        }

        $now = Carbon::now();

        if ($coupon->start_at && $now->lt($coupon->start_at)) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon is not active yet.',
            ]);
        }

        if ($coupon->end_at && $now->gt($coupon->end_at)) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon has expired.',
            ]);
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupon usage limit reached.',
            ]);
        }

        $minOrderAmount = $coupon->minOrderAmountFor($currency);
        if ($minOrderAmount && $orderTotal < $minOrderAmount) {
            throw ValidationException::withMessages([
                'coupon' => "Minimum order amount is {$minOrderAmount} {$currency}.",
            ]);
        }

        $alreadyUsed = $coupon->coupon_usages()->where('user_id', $user->id)
                                ->whereHas('order', function ($q) {
                                    $q->where('payment_status', 'paid');
                                })
                                ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages([
                'coupon' => 'You already used this coupon.',
            ]);
        }

        return $coupon;
    }


    public function calculateDiscount(Coupon $coupon, float $orderTotal , string $currency = 'EGP'): float
    {
        if ($coupon->discount_type === 'percentage') {
            $discount = ($coupon->discountValueFor($currency) / 100) * $orderTotal;
        } else {
            $discount = $coupon->discountValueFor($currency);
        }

        return min($discount, $orderTotal);
    }

    public function recordUsage(Coupon $coupon, int $orderId, int $userId, float $totalBefore, float $discountValue): void
    {
        $coupon->coupon_usages()->create([
            'order_id'                   => $orderId,
            'user_id'                    => $userId,
            'total_order_before_discound' => $totalBefore,
            'value_discound'             => $discountValue,
        ]);

        $coupon->increment('used_count');
    }
}
