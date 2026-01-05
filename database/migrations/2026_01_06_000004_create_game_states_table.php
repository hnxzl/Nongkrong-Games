<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel game_states: Menyimpan state detail game
     * - Kartu di tangan pemain (Uno)
     * - Kata yang didapat (Undercover, Last Letter)
     * - Jawaban pemain (ABC 5 Dasar)
     */
    public function up(): void
    {
        Schema::create('game_states', function (Blueprint $table) {
            $table->id();
            $table->uuid('game_session_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state_type', 50); // hand, deck, discard, word, answer, vote
            $table->json('state_data'); // Data state dalam JSON
            $table->timestamps();
            
            $table->foreign('game_session_id')->references('id')->on('game_sessions')->onDelete('cascade');
            $table->index(['game_session_id', 'state_type']);
            $table->index(['game_session_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_states');
    }
};
