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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->dateTime('start_at') ;
            $table->dateTime('end_at');
            $table->decimal('price', 12, 2); // عشان لو سعر الباقة اتغير يعدين
            $table->enum('status', ['active', 'inactive', 'expired'])->default('inactive');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'gift'])->default('pending');
            $table->dateTime('canceled_at')->nullable();
            $table->string('ended_reason')->nullable(); // expired , canceled_by_user , paid failed
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
