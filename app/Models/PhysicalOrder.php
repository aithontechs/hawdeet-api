<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhysicalOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'shipping_address_id',
        'delivery_status',
        'shipping_cost',
        'tracking_number',
        'shipping_provider',
        'shipped_at',
        'delivered_at',
        'notes',

    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingAddress()
    {
        return $this->belongsTo(ShippingAddress::class, 'shipping_address_id');
    }




}
