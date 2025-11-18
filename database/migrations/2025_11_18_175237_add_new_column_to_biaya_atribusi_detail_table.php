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
        Schema::table('biaya_atribusi_detail', function (Blueprint $table) {
            $table->integer('bilangan')->nullable()->after('bulan');
            $table->integer('kode_petugas')->nullable()->after('keterangan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biaya_atribusi_detail', function (Blueprint $table) {
            $table->dropColumn('bilangan');
            $table->dropColumn('kode_petugas');
        });
    }
};
