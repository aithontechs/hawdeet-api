<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

        const MAX_DEPTH = 2;

    protected $fillable = [
        'post_id', 'parent_id', 'body', 'depth',
        'likes_count', 'replies_count',
    ];

    public function commentable()
    {
        return $this->morphTo();
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
                    ->orderBy('created_at');
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function canReply(): bool
    {
        return $this->depth < self::MAX_DEPTH;
    }
}
