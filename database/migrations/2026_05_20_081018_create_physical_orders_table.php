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
        Schema::create('physical_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_address_id')->nullable()->constrained('shipping_addresses')->nullOnDelete();

            $table->enum('delivery_status', [
                'pending', 'confirmed', 'processing',
                'shipped', 'out_for_delivery',
                'delivered', 'returned', 'cancelled',
            ])->default('pending');

            $table->decimal('shipping_cost', 10, 2)->default(0); 
            $table->string('tracking_number')->nullable();
            $table->string('shipping_provider')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_orders');
    }
};
