<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->enum('item_type', ['digital', 'physical'])->default('digital')->after('book_id');
            $table->unsignedInteger('quantity')->default(1)->after('item_type');

            $table->unique(['cookie_id', 'book_id', 'item_type']);
            $table->unique(['user_id',   'book_id', 'item_type']);
        });
    }


    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'item_type')) {
                $table->dropColumn('item_type');
            }

            if (Schema::hasColumn('carts', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }
};
