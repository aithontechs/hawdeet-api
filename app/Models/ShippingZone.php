<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country',
        'cost',
        'cost_usd',
        'days_min',
        'days_max',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'cost_usd' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];


    public function costFor(string $currency = 'EGP'): float
    {
        if ($currency === 'USD') {
            return (float) ($this->cost_usd ?? $this->cost);
        }
        return (float) $this->cost;
    }
}
