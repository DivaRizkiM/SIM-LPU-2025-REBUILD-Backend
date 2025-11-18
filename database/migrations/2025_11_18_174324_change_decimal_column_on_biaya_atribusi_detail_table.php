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
        Schema::table('biaya_atribusi', function (Blueprint $table) {
            $table->decimal('total_biaya', 30, 10)->change();
        });

        Schema::table('biaya_atribusi_detail', function (Blueprint $table) {
            $table->decimal('pelaporan', 30, 10)->change();
            $table->decimal('verifikasi', 30, 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biaya_atribusi', function (Blueprint $table) {
            $table->decimal('total_biaya', 45, 0)->change();
        });
        
        Schema::table('biaya_atribusi_detail', function (Blueprint $table) {
            $table->decimal('pelaporan', 45, 0)->change();
            $table->decimal('verifikasi', 45, 0)->change();
        });
    }
};
