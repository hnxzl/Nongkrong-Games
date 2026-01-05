<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel abc_categories: Kategori untuk game ABC 5 Dasar
     */
    public function up(): void
    {
        Schema::create('abc_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Buah, Negara, Nama Orang, dll
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abc_categories');
    }
};
