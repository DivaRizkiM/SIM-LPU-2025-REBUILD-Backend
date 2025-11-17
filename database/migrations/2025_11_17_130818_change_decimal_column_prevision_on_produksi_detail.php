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
        Schema::table('produksi_detail', function (Blueprint $table) {
            $table->decimal('tpkirim', 30, 10)->change();
            $table->decimal('pelaporan', 30, 10)->change();
            $table->decimal('bsu_bruto', 30, 10)->change();
            $table->decimal('pelaporan_prognosa', 30, 10)->change();
            $table->decimal('bsu_bruto_prognosa', 30, 10)->change();
            $table->decimal('verifikasi', 30, 10)->change();
            $table->decimal('total', 30, 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_detail', function (Blueprint $table) {
            $table->decimal('tpkirim', 18, 2)->change();
            $table->decimal('pelaporan', 18, 2)->change();
            $table->decimal('bsu_bruto', 18, 2)->change();
            $table->decimal('pelaporan_prognosa', 18, 2)->change();
            $table->decimal('bsu_bruto_prognosa', 18, 2)->change();
            $table->decimal('verifikasi', 18, 2)->change();
            $table->decimal('total', 18, 2)->change();
        });
    }
};
