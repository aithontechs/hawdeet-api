<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'discount_value_usd',
        'start_at',
        'end_at',
        'max_uses',
        'used_count',
        'min_order_amount',
        'min_order_amount_usd',
        'status',
    ];

    protected $casts = [
        'discount_value'   => 'decimal:2',
        'discount_value_usd'   => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'min_order_amount_usd' => 'decimal:2',
        'start_at'         => 'datetime',
        'end_at'           => 'datetime',
    ];




    public function coupon_usages()
    {
        return $this->hasMany(CouponUsage::class) ;
    }


    public function discountValueFor(string $currency = 'EGP'): float
    {
        if ($currency === 'USD') {
            return (float) ($this->discount_value_usd ?? $this->discount_value);
        }
        return (float) $this->discount_value;
    }

    public function minOrderAmountFor(string $currency = 'EGP'): ?float
    {
        if ($currency === 'USD') {
            return $this->min_order_amount_usd !== null
                ? (float) $this->min_order_amount_usd
                : (float) $this->min_order_amount;
        }
        return $this->min_order_amount !== null ? (float) $this->min_order_amount : null;
    }

}
