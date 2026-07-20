<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name' ,
        'duration_months',
        'price',
        'price_usd',
        'compare_price' ,
        'compare_price_usd' ,
        'description' ,
        'is_active'
    ];

    protected $casts = [
        'price'=> 'decimal:2',
        'compare_price'=> 'decimal:2',
        'price_usd'=> 'decimal:2',
        'compare_price_usd'=> 'decimal:2',
        'is_active' => 'boolean',
        'description' => 'array',
    ] ;

    public $hidden = ['created_at' , 'updated_at'];


    public function scopeActive()
    {
        return $this->where('is_active', true) ;
    }

    public function priceFor(string $currency = 'EGP'): float
    {
        if ($currency === 'USD') {
            return (float) ($this->price_usd ?? $this->price);
        }
        return (float) $this->price;
    }

    public function comparePriceFor(string $currency = 'EGP'): ?float
    {
        if ($currency === 'USD') {
            return $this->compare_price_usd !== null
                ? (float) $this->compare_price_usd
                : ($this->compare_price !== null ? (float) $this->compare_price : null);
        }
        return $this->compare_price !== null ? (float) $this->compare_price : null;
    }

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }
}
