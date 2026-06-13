<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('plan_id')->constrained('coupons')->nullOnDelete();
            $table->decimal('original_amount', 10, 2)->nullable()->after('price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('original_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['coupon_id' , 'original_amount' , 'discount_amount']) ;
        });
    }
};
