<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'discount',
        'total',
        'payment_method',
        'payment_status',
        'paid_at',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'discount'       => 'decimal:2',
        'total'          => 'decimal:2',
        'paid_at'        => 'datetime',
    ];


    public function scopeFilter(Builder $builder , $request)
    {
        $builder->when($request->filled('name') , function($query , $name){
            $query->whereHas('user' , function($query) use ($name){
                $query->where('name','like','%'.$name.'%');
            }) ;
        });

        $builder->when($request->filled('order_number') , function($query , $order_number){
            $query->where('order_number','like',"%{$order_number}%") ;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class , 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class , 'order_id');
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public static function generateOrderNumber()
    {
        $year  = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;
        return "ORD-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

}
