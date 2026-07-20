<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->decimal('price_usd', 12, 2)->nullable()->after('price');
            $table->decimal('compare_price_usd', 12, 2)->nullable()->after('compare_price');
            $table->decimal('physical_price_usd', 12, 2)->nullable()->after('physical_price');
            $table->decimal('physical_compare_price_usd', 12, 2)->nullable()->after('physical_compare_price');
            $table->decimal('physical_hard_cover_price_usd', 12, 2)->nullable()->after('physical_hard_cover_price');
            $table->decimal('physical_hard_cover_compare_price_usd', 12, 2)->nullable()->after('physical_hard_cover_compare_price');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['price_usd', 'compare_price_usd', 'physical_price_usd', 'physical_compare_price_usd', 'physical_hard_cover_price_usd', 'physical_hard_cover_compare_price_usd']);
        });
    }
};
