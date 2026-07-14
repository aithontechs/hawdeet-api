<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->decimal('physical_hard_cover_price', 12, 2)->nullable()->after('physical_stock');
            $table->decimal('physical_hard_cover_compare_price', 12, 2)->nullable()->after('physical_hard_cover_price');
            $table->unsignedInteger('physical_hard_cover_stock')->default(0)->after('physical_hard_cover_compare_price');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['physical_hard_cover_price', 'physical_hard_cover_compare_price', 'physical_hard_cover_stock']);
        });
    }
};
