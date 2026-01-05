<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel game_sessions: Menyimpan sesi permainan aktif
     * - Setiap room bisa memiliki banyak sesi (ronde)
     * - Menyimpan state umum game
     */
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('room_id');
            $table->integer('round_number')->default(1);
            $table->foreignId('current_player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('phase', [
                'setup',           // Persiapan game
                'playing',         // Game berlangsung
                'voting',          // Fase voting (Undercover)
                'discussion',      // Fase diskusi (Undercover)
                'answering',       // Fase menjawab (ABC 5 Dasar)
                'scoring',         // Fase penilaian
                'finished'         // Game selesai
            ])->default('setup');
            $table->integer('turn_number')->default(0);
            $table->enum('direction', ['clockwise', 'counter_clockwise'])->default('clockwise'); // Untuk Uno
            $table->timestamp('turn_started_at')->nullable();
            $table->integer('turn_time_limit')->default(30); // Detik
            $table->json('meta_data')->nullable(); // Data tambahan spesifik game
            $table->timestamps();
            
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->index(['room_id', 'phase']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
