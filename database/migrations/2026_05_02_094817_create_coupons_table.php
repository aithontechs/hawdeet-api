<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('discount_type' , ['fixed' , 'percentage']) ;
            $table->decimal('discount_value') ;
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedInteger('max_uses');
            $table->unsignedInteger('used_count')->default(0);;
            $table->decimal('min_order_amount')->nullable();
            $table->enum('status' , ['active' , 'inactive'])->default('active') ;
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
