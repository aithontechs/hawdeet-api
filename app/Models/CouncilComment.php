<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouncilComment extends Model
{
    use HasFactory ;

    const MAX_DEPTH = 1;

    protected $table = 'council_comments';

    protected $fillable = [
        'reading_council_id',
        'user_id',
        'parent_id',
        'body',
        'depth',
        'likes_count',
        'replies_count',
    ];


    public function council()
    {
        return $this->belongsTo(ReadingCouncil::class, 'reading_council_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(CouncilComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(CouncilComment::class, 'parent_id')
                    ->orderBy('created_at');
    }

    public function latestReplies()
    {
        return $this->hasMany(CouncilComment::class, 'parent_id')
                    ->with('user:id,name,avatar_url')
                    ->orderBy('created_at')
                    ->limit(2);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }


    public function canReply(): bool
    {
        return $this->depth < self::MAX_DEPTH;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

}
