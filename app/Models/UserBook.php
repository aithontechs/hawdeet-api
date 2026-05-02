<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBook extends Model
{
    use HasFactory;
    protected $fillable = ['user_id' , 'book_id' , 'access_type' , 'order_item_id' , 'user_subscription_id' , 'expires_at' , 'granted_at'] ;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function userSubscription()
    {
        return $this->belongsTo(UserSubscription::class);
    }


    public function scopeActive(Builder $query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired(Builder $query)
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    public function isActive(): bool
    {
        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return !$this->isActive();
    }

    public function isPurchase(): bool
    {
        return $this->access_type === 'purchase';
    }

    public function isSubscription(): bool
    {
        return $this->access_type === 'subscription';
    }

    public function daysRemaining(): ?int
    {
        if (is_null($this->expires_at)) return null;
        return max(0, now()->diffInDays($this->expires_at, false));
    }

}
