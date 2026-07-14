<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cookie_id',
        'book_id',
        'item_type',
        'quantity',
        'cover_type'
    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class , 'book_id');
    }

    public function scopeForSession(Builder $query, ?string $cookieId = null , ?int $userId = null)
    {
        return $query->when($userId, function ($q) use ($userId) {
            $q->where('user_id', $userId);
        }, function ($q) use ($cookieId) {
            $q->where('cookie_id', $cookieId);
        });
    }




}
