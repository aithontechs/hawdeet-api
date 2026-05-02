<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject , MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'birth_date',
        'password',
        'avatar_url',
        'social_provider',
        'social_id',
        'is_author',
        'is_active',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed'
    ];


    public function scopeSearch($query, $value)
    {
        if (!$value) return $query;

        return $query->where(function ($q) use ($value) {
            $q->where('name', 'like', "%$value%")
                ->orWhere('email', 'like', "%$value%")
                ->orWhere('phone', 'like', "%$value%");
        });
    }

    public function scopeType($query, $type)
    {
        if (!$type || $type === 'all') {
            return $query;
        }

        if ($type === 'author') {
            return $query->where('is_author' , true);
        }

        return $query;
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function userBooks()
    {
        return $this->hasMany(UserBook::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }


    public function activeSubscription(): ?UserSubscription
    {
        return $this->subscriptions()
                        ->where('status', 'active')
                        ->where('end_at', '>', now())
                        ->latest()
                        ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return !is_null($this->activeSubscription());
    }

    public function hasAccessToBook(int $bookId): bool
    {
        return $this->userBooks()
            ->where('book_id', $bookId)
            ->active()
            ->exists();
    }



    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }


    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }
}
