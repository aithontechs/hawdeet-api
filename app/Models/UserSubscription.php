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
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'canceled_at' => 'datetime',
        'price' => 'decimal:2',
    ];


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
