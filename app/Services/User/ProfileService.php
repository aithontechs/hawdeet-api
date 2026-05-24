<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Book\BookReadingProgressService;
use Illuminate\Support\Facades\Cache;

class ProfileService
{
    const CACHE_TTL = 300 ;
    
    public function __construct(
        private readonly BookReadingProgressService $progressService
    ) {}

    public function getProfileStats(User $user): array
    {
        return Cache::remember("profile_stats:{$user->id}", self::CACHE_TTL, function () use ($user) {

            $user->loadCount(['followers', 'following']);

            return [
                'followers_count' => $user->followers_count,
                'following_count' => $user->following_count,
                'reading_stats'   => $user->is_author
                    ? null
                    : $this->progressService->getProfileStats($user),
            ];
        });
    }

    public function clearCache(int $userId): void
    {
        Cache::forget("profile_stats:{$userId}");
    }
}
