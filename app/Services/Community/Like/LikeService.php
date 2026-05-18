<?php

namespace App\Services\Community\Like ;

use App\Models\Admin;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Cache\RateLimiter;

class LikeService
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function toggle(User|Admin $actor, Post|Comment $likeable)
    {
        $key = $this->rateLimitKey($actor, $likeable);

        if ($this->limiter->tooManyAttempts($key, maxAttempts: 3)) {
            $seconds = $this->limiter->availableIn($key);
            abort(429, "You are blocked from liking . Try again after $seconds seconds.");       
        }
        
        $this->limiter->hit($key, decaySeconds: 20);
        $existing = Like::query()->where('liker_type', get_class($actor))
                            ->where('liker_id',   $actor->id)
                            ->where('likeable_type', get_class($likeable))
                            ->where('likeable_id',   $likeable->id)
                            ->first();

        if ($existing) {
            $existing->delete();
            $likeable->decrement('likes_count');
            return ['liked' => false, 'likes_count' => $likeable->likes_count];
        }

        Like::create([
            'liker_type'    => get_class($actor),
            'liker_id'      => $actor->id,
            'likeable_type' => get_class($likeable),
            'likeable_id'   => $likeable->id,
        ]);
        $likeable->increment('likes_count');
        return ['liked' => true, 'likes_count' => $likeable->likes_count];
    }

    private function rateLimitKey(User|Admin $actor, Post|Comment $likeable): string
    {
        $actorType = class_basename($actor);
        $likeableType = class_basename($likeable); 

        return "like:{$actorType}_{$actor->id}:{$likeableType}_{$likeable->id}";
    }
}