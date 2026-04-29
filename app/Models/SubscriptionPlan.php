<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name' ,
        'duration_months',
        'price',
        'compare_price' ,
        'description' ,
        'is_active'
    ];

    protected $casts = [
        'price'=> 'decimal:2',
        'compare_price'=> 'decimal:2',
        'is_active' => 'boolean'
    ] ;
}
