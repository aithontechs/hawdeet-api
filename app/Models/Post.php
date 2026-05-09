<?php

namespace App\Models;

use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'body', 'media_url', 'media_type',
        'is_published', 'likes_count', 'comments_count',
        'shares_count', 'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $hidden = [
        'postable_type',
        'postable_id',
    ];

    // Author = User(author) or Admin
    public function postable()
    {
        return $this->morphTo();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id')
                    ->whereNull('parent_id')   // top-level only
                    ->orderByDesc('created_at');
    }

    public function allComments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function shares()
    {
        return $this->hasMany(Share::class, 'post_id');
    }

    public function isOwnedBy(User|Admin $actor): bool
    {
        return $this->postable_type === get_class($actor)
            && $this->postable_id === $actor->id;
    }


    public function isVisible(): bool
    {
        return $this->is_published && $this->is_approved ;
    }



    public function getMediaUrlAttribute(?string $value): ?string
    {
        if (!$value) return null;

        if (str_starts_with($value, 'http')) return $value;

        return Storage::disk(StorageService::DISK_PUBLIC)->url($value);
    }
}
