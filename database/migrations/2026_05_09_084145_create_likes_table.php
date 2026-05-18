<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->morphs('likeable');     // Post or Comment
            $table->morphs('liker');        // User or Admin          
            $table->timestamps();
            $table->unique(['likeable_type', 'likeable_id', 'liker_type', 'liker_id']);        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
