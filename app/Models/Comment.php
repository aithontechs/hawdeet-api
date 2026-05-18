<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    const MAX_DEPTH = 2;

    protected $fillable = [
        'post_id', 'parent_id','commentable_type' , 'commentable_id', 'body', 'depth',
        'likes_count', 'replies_count',
    ];

    public $hidden = ['commentable_id' , 'commentable_type'];

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

    public function latestReplies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
                    ->with('commentable:id,name,avatar_url')
                    ->orderBy('created_at')
                    ->limit(2);
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

    public function isOwnedBy(User|Admin $actor): bool
    {
        return $this->commentable_type === get_class($actor)
            && $this->commentable_id === $actor->id;
    }
}
