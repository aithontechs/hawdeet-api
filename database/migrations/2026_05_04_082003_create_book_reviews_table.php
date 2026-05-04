<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('book_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->unique(['user_id', 'book_id']);
            $table->boolean('is_approve')->default(true);
            $table->timestamps();
            $table->index(['book_id', 'is_approve']);
            $table->index(['book_id', 'rating']);
        });

    }


    public function down(): void
    {
        Schema::dropIfExists('book_reviews');
    }
};
