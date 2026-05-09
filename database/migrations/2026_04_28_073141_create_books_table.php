<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->text('description');
            $table->string('cover');
            $table->string('file');
            $table->string('preview')->nullable();
            $table->decimal('price' , 12 , 2)->default(0.0);
            $table->decimal('compare_price' , 12 , 2)->nullable() ;
            $table->unsignedInteger('age_min');
            $table->unsignedInteger('total_pages')->default(0);
            $table->float('avg_rating')->default(0) ;
            $table->unsignedInteger('reviews_count')->default(0) ;
            $table->boolean('is_free')->default(0);
            $table->boolean('published')->default(0);
            $table->dateTime('published_at')->nullable() ;
            $table->foreignId('uploaded_by')->nullable()->references('id')->on('admins')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
