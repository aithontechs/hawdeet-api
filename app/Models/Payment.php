<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'paymob_order_id',
        'user_subscription_id',
        'amount',
        'currency',
        'type',
        'status',
        'payment_gateway',
        'gateway_transaction_id',
        'gateway_response',
        'paid_at',
        'refunded_at',
        'failure_reason',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
        'refunded_at'      => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription()
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }



    public function getPayableAttribute()
    {
        return $this->order ?? $this->subscription;
    }


    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool { return $this->status === 'pending'; }


    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }
}
