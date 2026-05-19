<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('item_type', ['digital', 'physical'])->default('digital')->after('price');
            $table->unsignedInteger('quantity')->default(1)->after('item_type') ;
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
            $table->dropColumn('quantity');
        });
    }
};
