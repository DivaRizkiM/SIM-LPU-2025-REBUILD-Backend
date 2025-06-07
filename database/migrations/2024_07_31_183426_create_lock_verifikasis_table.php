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
        Schema::create('lock_verifikasis', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun'); // Kolom untuk tahun
            $table->unsignedTinyInteger('bulan'); // Kolom untuk bulan
            $table->boolean('status'); // Kolom untuk status true/false
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lock_verifikasis');
    }
};
