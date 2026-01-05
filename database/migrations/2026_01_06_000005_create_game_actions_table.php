<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel game_actions: Log semua aksi dalam game
     * - Untuk replay dan debugging
     * - Audit trail permainan
     */
    public function up(): void
    {
        Schema::create('game_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('game_session_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 50); // play_card, draw_card, vote, submit_word, etc
            $table->json('action_data')->nullable();
            $table->integer('turn_number');
            $table->timestamps();
            
            $table->foreign('game_session_id')->references('id')->on('game_sessions')->onDelete('cascade');
            $table->index(['game_session_id', 'turn_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_actions');
    }
};
