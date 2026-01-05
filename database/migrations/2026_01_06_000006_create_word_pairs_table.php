<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel word_pairs: Pasangan kata untuk game Undercover
     * - civilian_word: Kata untuk Civilian
     * - undercover_word: Kata untuk Undercover (mirip tapi beda)
     */
    public function up(): void
    {
        Schema::create('word_pairs', function (Blueprint $table) {
            $table->id();
            $table->string('civilian_word', 100);
            $table->string('undercover_word', 100);
            $table->string('category', 50)->nullable(); // makanan, tempat, benda, dll
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_pairs');
    }
};
