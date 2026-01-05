<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel room_players: Relasi many-to-many antara users dan rooms
     * - Menyimpan status pemain dalam room
     * - Menyimpan skor dan urutan giliran
     */
    public function up(): void
    {
        Schema::create('room_players', function (Blueprint $table) {
            $table->id();
            $table->uuid('room_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('turn_order')->nullable(); // Urutan giliran pemain
            $table->integer('score')->default(0);
            $table->enum('status', ['active', 'eliminated', 'disconnected'])->default('active');
            $table->boolean('is_ready')->default(false);
            $table->string('role', 50)->nullable(); // Untuk Undercover: civilian, undercover, mr_white
            $table->timestamps();
            
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->unique(['room_id', 'user_id']);
            $table->index('room_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_players');
    }
};
