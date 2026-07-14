<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->unique(
                ['cookie_id', 'book_id', 'item_type', 'cover_type'],
                'carts_cookie_id_book_id_item_type_cover_type_unique'
            );

            $table->unique(
                ['user_id', 'book_id', 'item_type', 'cover_type'],
                'carts_user_id_book_id_item_type_cover_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique('carts_cookie_id_book_id_item_type_cover_type_unique');
            $table->dropUnique('carts_user_id_book_id_item_type_cover_type_unique');
        });
    }
};
