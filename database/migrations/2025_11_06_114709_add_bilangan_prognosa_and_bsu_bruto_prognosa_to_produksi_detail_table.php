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
            $table->decimal('bsu_bruto_prognosa', 18, 2)->nullable()->after('pelaporan_prognosa');
            $table->integer('bilangan_prognosa')->nullable()->after('bsu_bruto_prognosa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_detail', function (Blueprint $table) {
            $table->dropColumn('bilangan_prognosa');
            $table->dropColumn('bsu_bruto_prognosa');
        });
    }
};
