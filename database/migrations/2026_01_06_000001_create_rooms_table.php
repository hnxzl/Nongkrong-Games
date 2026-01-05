<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel rooms: Menyimpan data room/lobby game
     * - Setiap room memiliki kode unik untuk join
     * - Status: waiting, playing, finished
     * - Game type: uno, undercover, last_letter, abc_dasar
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 6)->unique(); // Kode room 6 karakter (ABC123)
            $table->string('name', 100);
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->enum('game_type', ['uno', 'undercover', 'last_letter', 'abc_dasar']);
            $table->enum('status', ['waiting', 'playing', 'finished'])->default('waiting');
            $table->integer('max_players')->default(10);
            $table->integer('min_players')->default(2);
            $table->json('settings')->nullable(); // Pengaturan spesifik per game
            $table->timestamps();
            
            $table->index(['code', 'status']);
            $table->index('host_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
