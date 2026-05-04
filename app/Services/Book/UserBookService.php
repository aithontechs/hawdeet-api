<?php

namespace App\Services\Book ;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserBookService
{

    const CACHE_TTL = 3600 ;

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

    public function clearCache(int $userId): void
    {
        Cache::forget("user_books:{$userId}");
    }

}
