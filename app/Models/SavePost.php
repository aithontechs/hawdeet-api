<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavePost extends Model
{
    use HasFactory;

    protected $fillable = [
        'saver_type',
        'saver_id',
        'post_id',
    ];

    public $hidden = ['saver_type' , 'updated_at' ,'saver_id' , 'post_id'];

    public function saver()
    {
        return $this->morphTo();
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
