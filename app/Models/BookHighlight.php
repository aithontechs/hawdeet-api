<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookHighlight extends Model
{
    protected $fillable = [
        'user_id',
        'book_id',
        'page_number',
        'selected_text',
        'color',
        'position_data',
    ];

    protected $casts = [
        'position_data' => 'array',
        'page_number'   => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
