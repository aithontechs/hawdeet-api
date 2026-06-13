<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'start_at',
        'end_at',
        'price',
        'status',
        'payment_status',
        'canceled_at',
        'ended_reason',
        'discount_amount',
        'original_amount',
        'coupon_id'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'canceled_at' => 'datetime',
        'price' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public $hidden = ['updated_at'];


    public function scopeStatus(Builder $builder , $status)
    {
        $builder->when($status , function($builder , $status) {
            $builder->where('status', $status) ;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function coupon()                
    {
        return $this->belongsTo(Coupon::class);
    }


    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->end_at->isPast();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }


}
