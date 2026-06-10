<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
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
        'email_verified_at',
        'bio',
        'email_verification_otp' ,
        'otp_expires_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_otp',
        'otp_expires_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed'
    ];

    // public $appends = ['avatar_url'];


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


    public function following()
    {
        return $this->belongsToMany(
            User::class, 'followers', 'follower_id', 'following_id'
        )->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(
            User::class, 'followers', 'following_id', 'follower_id'
        )->withTimestamps();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function userBooks()
    {
        return $this->hasMany(UserBook::class);
    }

    public function authorBooks()
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function shippingAddresses()
    {
        return $this->hasMany(ShippingAddress::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestChatMessage()
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function readingProgress()
    {
        return $this->hasMany(BookReadingProgress::class);
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



    public function follow(int $userId): void
    {
        if ($userId === $this->id) return;
        $this->following()->syncWithoutDetaching([$userId]);
    }

    public function unfollow(int $userId): void
    {
        $this->following()->detach($userId);
    }


    public function toggleFollow(int $userId): string
    {
        if ($this->isFollowing($userId)) {
            $this->unfollow($userId);
            return 'unfollowed';
        }
        $this->follow($userId);
        return 'followed';
    }

    public function isFollowing(int $userId): bool
    {
        return $this->following()->where('following_id', $userId)->exists();
    }

    public function isFollowedBy(int $userId): bool
    {
        return $this->followers()->where('follower_id', $userId)->exists();
    }

    public function isMutualFollow(int $userId): bool
    {
        return $this->isFollowing($userId) && $this->isFollowedBy($userId);
    }

    public function followersCount(): int
    {
        return $this->followers()->count();
    }

    public function followingCount(): int
    {
        return $this->following()->count();
    }



    public function getAvatarUrlAttribute()
    {
        if (!$this->attributes['avatar_url']) {
            return null;
        }

        $avatar = $this->attributes['avatar_url'];

        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($avatar);
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
        $otp = (string) random_int(100000, 999999);
        $this->update([
            'email_verification_otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10)
        ]);

        $this->notify(new VerifyEmailNotification($otp));
    }
}
