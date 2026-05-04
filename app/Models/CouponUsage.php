<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = ['coupon_id' , 'order_id' , 'user_id' , 'total_order_before_discound' , 'value_discound'];
    public $hidden = ['created_at' , 'updated_at'] ;



    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
