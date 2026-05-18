<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;

    protected $fillable = ['liker_id' , 'liker_type' , 'likeable_type' , 'likeable_id' ]; // likeable_type , likeable_id

    public function likeable()
    {
        return $this->morphTo();
    }

    public function liker()
    {
        return $this->morphTo();
    }
}
