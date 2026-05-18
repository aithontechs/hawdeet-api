<?php

namespace App\Services\Community\Share ;

use App\Models\{Post, Share, User};
use App\Models\Admin;

class ShareService
{
    public function share(User|Admin $actor, Post $post): array
    {
        abort_unless($post->isVisible(), 403, 'Cannot share this post.');

        $alreadyShared = Share::query()
            ->where('post_id',     $post->id)
            ->where('sharer_type', get_class($actor))
            ->where('sharer_id',   $actor->id)
            ->exists();

        if (!$alreadyShared) {
            Share::create([
                'post_id'     => $post->id,
                'sharer_type' => get_class($actor),
                'sharer_id'   => $actor->id,
            ]);

            $post->increment('shares_count');
        }

        return [
            'shared'       => true,
            'shares_count' => $alreadyShared
                ? $post->shares_count
                : $post->shares_count ,
        ];
    }

    public function unshare(User|Admin $actor, Post $post): array
    {
        $share = Share::query()
            ->where('post_id',     $post->id)
            ->where('sharer_type', get_class($actor))
            ->where('sharer_id',   $actor->id)
            ->first();

        abort_if(is_null($share), 404, 'You have not shared this post.');

        $share->delete();
        $post->decrement('shares_count');

        return [
            'unshared'       => true ,
            'shares_count' => $post->shares_count ,
        ];
    }
}