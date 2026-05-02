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
        'start_at',
        'end_at',
        'max_uses',
        'used_count',
        'min_order_amount',
        'status',
    ];

    protected $casts = [
        'discount_value'   => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'start_at'         => 'datetime',
        'end_at'           => 'datetime',
    ];




    public function coupon_usages()
    {
        return $this->hasMany(CouponUsage::class) ;
    }

}
