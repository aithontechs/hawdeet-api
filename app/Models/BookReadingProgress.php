<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookReadingProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'current_page',
        'total_pages',
        'percentage',
        'status',
        'started_at',
        'last_read_at',
        'completed_at',
    ];

    protected $casts = [
        'percentage'   => 'float',
        'started_at'   => 'datetime',
        'last_read_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class) ;
    }

    public function book()
    {
        return $this->belongsTo(Book::class) ;
    }

    public function isCompleted()
    {
        return $this->status === 'completed' ;
    }

}
