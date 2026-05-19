<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_cost', 10, 2)->default(0)->after('subtotal');
            $table->boolean('has_physical')->default(0)->after('order_number');
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_cost');
            $table->dropColumn('has_physical');
        });
    }
};
