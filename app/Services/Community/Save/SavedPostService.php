<?php

namespace App\Services\Community\Save;

use App\Models\Admin;
use App\Models\Post;
use App\Models\SavePost;
use App\Models\User;

class SavedPostService
{
    public function toggle(User|Admin $actor, Post $post): array
    {
        $existing = SavePost::query()->where('saver_type', get_class($actor))->where('saver_id',   $actor->id)->where('post_id',    $post->id)->first();

        if ($existing) {
            $existing->delete();
            return ['saved' => false];
        }

        SavePost::create([
            'saver_type' => get_class($actor),
            'saver_id'   => $actor->id,
            'post_id'    => $post->id,
        ]);

        return ['saved' => true];
    }

    public function getSavedPosts(User|Admin $actor, string $paginationType = 'cursor')
    {
        $query = SavePost::query()->where('saver_type', get_class($actor))->where('saver_id',   $actor->id)
            ->with([
                'post' => fn ($q) => $q->with('postable:id,name,avatar_url')
                                       ->where('is_published', true)
                                       ->where('is_approved',  true),
            ])
            ->latest();

        return match($paginationType) {
            'simple' => $query->simplePaginate(10),
            'cursor' => $query->cursorPaginate(10),
            default  => $query->paginate(10),
        };
    }

    public function isSaved(User|Admin $actor, Post $post): bool
    {
        return SavePost::query()->where('saver_type', get_class($actor))->where('saver_id',   $actor->id)->where('post_id',    $post->id)->exists();
    }
}
