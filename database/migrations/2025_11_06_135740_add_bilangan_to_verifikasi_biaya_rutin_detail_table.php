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
        Schema::table('verifikasi_biaya_rutin_detail', function (Blueprint $table) {
            $table->integer('bilangan')->nullable()->after('pelaporan');
            $table->integer('bilangan_prognosa')->nullable()->after('pelaporan_prognosa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verifikasi_biaya_rutin_detail', function (Blueprint $table) {
            $table->dropColumn('bilangan');
            $table->dropColumn('bilangan_prognosa');
        });
    }
};
