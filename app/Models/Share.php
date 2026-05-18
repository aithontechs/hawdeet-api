<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    use HasFactory;

    protected $fillable = ['post_id', 'sharer_type', 'sharer_id'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function sharer()
    {
        return $this->morphTo();
    }
}
