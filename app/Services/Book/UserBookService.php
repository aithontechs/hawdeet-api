<?php

namespace App\Services\Book;

use App\Models\{User, UserSubscription};
use App\Models\Book;
use App\Models\UserBook;
use Illuminate\Support\Facades\Cache;

class UserBookService
{
    const CACHE_TTL = 3600;

    public function getUserBookIds(User $user): array
    {
        return Cache::remember(
            "user_books:{$user->id}",
            self::CACHE_TTL,
            fn () => $user->userBooks()
                ->active()
                ->pluck('book_id')
                ->toArray()
        );
    }

    public function recordSubscriptionAccess(User $user, Book $book): void
    {
        $subscription = UserSubscription::query()
                            ->where('user_id', $user->id)
                            ->where('status', 'active')
                            ->where('end_at', '>', now())
                            ->first();

        if (!$subscription) return;

        UserBook::firstOrCreate(
            [
                'user_id'     => $user->id,
                'book_id'     => $book->id,
                'access_type' => 'subscription',
            ],
            [
                'user_subscription_id' => $subscription->id,
                'expires_at'           => $subscription->end_at,
                'granted_at'           => now(),
            ]
        );
        // $this->clearCache($user->id) ;
    }

    public function hasActiveSubscription(User $user): bool
    {
        return Cache::remember(
            "user_subscription:{$user->id}",
            self::CACHE_TTL,
            fn () => UserSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('end_at', '>', now())
                ->exists()
        );
    }

    public function clearCache(int $userId): void
    {
        Cache::forget("user_books:{$userId}");
        Cache::forget("user_subscription:{$userId}");
    }
}
