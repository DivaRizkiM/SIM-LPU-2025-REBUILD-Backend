<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes to optimize heavy SUM + JOIN queries on produksi_detail & produksi.
     * These queries were causing CPU 100% due to full table scans.
     */
    public function up(): void
    {
        // produksi: composite index for the most common WHERE clause (tahun + bulan)
        Schema::table('produksi', function (Blueprint $table) {
            $table->index(['tahun_anggaran', 'bulan'], 'idx_produksi_tahun_bulan');
            $table->index('id_kpc', 'idx_produksi_id_kpc');
        });

        // produksi_detail: FK index + columns used in WHERE/JOIN
        Schema::table('produksi_detail', function (Blueprint $table) {
            $table->index('id_produksi', 'idx_pd_id_produksi');
            $table->index('kategori_produksi', 'idx_pd_kategori_produksi');
            $table->index('keterangan', 'idx_pd_keterangan');
            $table->index('kode_rekening', 'idx_pd_kode_rekening');
            $table->index('nama_bulan', 'idx_pd_nama_bulan');
            // Composite index for the most frequent query pattern
            $table->index(['id_produksi', 'kategori_produksi'], 'idx_pd_produksi_kategori');
        });
    }

    public function down(): void
    {
        Schema::table('produksi', function (Blueprint $table) {
            $table->dropIndex('idx_produksi_tahun_bulan');
            $table->dropIndex('idx_produksi_id_kpc');
        });

        Schema::table('produksi_detail', function (Blueprint $table) {
            $table->dropIndex('idx_pd_id_produksi');
            $table->dropIndex('idx_pd_kategori_produksi');
            $table->dropIndex('idx_pd_keterangan');
            $table->dropIndex('idx_pd_kode_rekening');
            $table->dropIndex('idx_pd_nama_bulan');
            $table->dropIndex('idx_pd_produksi_kategori');
        });
    }
};
