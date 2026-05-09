<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('book_reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_page')->default(1);
            $table->unsignedSmallInteger('total_pages');
            $table->decimal('percentage', 5, 2)->default(0);
            $table->enum('status', ['reading', 'completed'])->default('reading');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_read_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->unique(['user_id', 'book_id']);
            $table->index(['user_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_reading_progress');
    }
};
