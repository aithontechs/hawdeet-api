<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'book_id', 'book_name' , 'price', 'access_duration_days',
    ];

    public $hidden = ['created_at' , 'updated_at'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function userBook()
    {
        return $this->belongsTo(UserBook::class);
    }

}
