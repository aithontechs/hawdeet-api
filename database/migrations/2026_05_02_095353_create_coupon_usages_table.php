<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->references('id')->on('coupons')->onDelete('cascade') ;
            $table->foreignId('order_id')->references('id')->on('orders')->onDelete('cascade') ;
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade') ;
            $table->decimal('total_order_before_discound') ; // snampshot
            $table->decimal('value_discound') ; // snapshot
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
