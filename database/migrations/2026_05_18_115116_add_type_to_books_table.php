<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->enum('type', ['digital', 'physical', 'both'])->default('digital')->after('preview');
            $table->decimal('physical_price', 12, 2)->nullable()->after('compare_price');
            $table->decimal('physical_compare_price', 12, 2)->nullable()->after('physical_price');
            $table->unsignedInteger('physical_stock')->default(0)->after('physical_compare_price');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['type', 'physical_price', 'physical_compare_price', 'physical_stock']);
        });
    }
};
