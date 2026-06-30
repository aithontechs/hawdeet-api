<?php

namespace App\Services\Community\Post ;

use App\Models\Admin;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Services\Storage\StorageService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

class PostService
{
    const MEDIA_FOLDER = 'posts';

    public function __construct(
        private readonly StorageService $storage
    ) {}

    public function createPost(User|Admin $actor, array $data , ?UploadedFile $media = null): Post
    {

        $mediaPath = null;
        $mediaType = null;

        if ($media) {
            $mediaPath = $this->storage->upload(
                $media,
                self::MEDIA_FOLDER,
                StorageService::DISK_PUBLIC,
            );
            $mediaType = $this->resolveMediaType($media);
        }

        $isAdmin = $actor instanceof Admin;
        $post = $actor->morphMany(Post::class, 'postable')->create([
            'body'         => $data['body'] ?? null,
            'media_url'    => $mediaPath,
            'media_type'   => $mediaType,
            'is_published' => $isAdmin ,
            'is_approved'  => $isAdmin ,
            'published_at' => $isAdmin ? now() : null,
        ]);

        if ($actor instanceof User) {
            $this->notifyFollowers($post, $actor);
        }

        return $post;
    }

    public function updatePost(Post $post, array $data, ?UploadedFile $media = null): Post
    {
        $mediaPath = $post->getRawOriginal('media_url');
        $mediaType = $post->media_type;

        if ($media) {
            $mediaPath = $this->storage->replace(
                $media,
                $mediaPath,
                self::MEDIA_FOLDER,
                StorageService::DISK_PUBLIC,
            );
            $mediaType = $this->resolveMediaType($media);
        }

        if (!empty($data['remove_media'])) {
            $this->storage->delete($mediaPath, StorageService::DISK_PUBLIC);
            $mediaPath = null;
            $mediaType = null;
        }

        $post->update([
            'body'       => $data['body']  ?? $post->body,
            'media_url'  => $mediaPath,
            'media_type' => $mediaType,
        ]);

        return $post->fresh();
    }

    public function deletePost(Post $post): void
    {
        $mediaPath = $post->getRawOriginal('media_url');

        if ($mediaPath) {
            $this->storage->delete($mediaPath, StorageService::DISK_PUBLIC);
        }

        $post->delete();
    }

    public function approvePost(Post $post): Post
    {
        abort_if($post->is_approved , 403 , 'Post already approved') ;
        $post->update([
            'is_approved' => 1 ,
            'is_published' => 1 ,
            'published_at' => now()
            ]);
        return $post->fresh();
    }

    public function getPosts(string $paginationType = 'cursor')
    {
        $query = Post::query()
            ->with('postable:id,name,avatar_url')
            ->where('is_published', true)
            ->where('is_approved',  true)
            ->orderByDesc('published_at');

        return match($paginationType) {
            'simple' => $query->simplePaginate(10),   // mobile
            'cursor' => $query->cursorPaginate(10),   // infinite scroll
            default  => $query->paginate(10),          // web
        };
    }

    public function getPendingPosts()
    {
        return Post::query()
            ->with('postable:id,name,avatar_url')
            ->where('is_approved', false)
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    private function resolveMediaType(UploadedFile $file): string
    {
        return str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';
    }

    private function notifyFollowers(Post $post, User $actor): void
    {
        $actor->followers()
            ->chunk(100, function ($followers) use ($post, $actor) {
                Notification::send($followers, new NewPostNotification($post, $actor));
            });
    }



    public function getPostsDashboard()
    {
        $query = Post::query()->with('postable:id,name')->orderByDesc('published_at')->paginate(15);
        return $query ;
    }
}
