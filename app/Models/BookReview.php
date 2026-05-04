<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'rating',
        'comment',
        'is_approve',
    ];

    protected $casts = [
        'rating'     => 'integer',
        'is_approve' => 'boolean',
    ];

    public function scopeApprove(Builder $query)
    {
        return $query->where('is_approve' , true ) ;
    }

    public function scopeForBook(Builder $query, int $bookId)
    {
        return $query->where('book_id', $bookId);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

}
