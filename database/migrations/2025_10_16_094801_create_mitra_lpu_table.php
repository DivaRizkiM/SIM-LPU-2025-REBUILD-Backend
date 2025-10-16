<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra_lpu', function (Blueprint $table) {
            $table->id();
            $table->char('id_kpc', 7)
                  ->comment('Nopend KPC (from request)');
            $table->string('nib', 15);
            $table->string('nama_mitra', 100);
            $table->string('alamat_mitra', 255);
            $table->string('kode_wilayah_kerja', 100);
            $table->string('nama_wilayah', 100);
            $table->char('nopend', 7)->comment('Nopend Kantor Mitra');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('long', 10, 7)->nullable();
            $table->string('nik', 20);
            $table->string('namafile', 255);
            $table->json('raw')->nullable();
            $table->index('id_kpc');
            $table->index('nopend');
            $table->index('nib');
            $table->unique(['id_kpc', 'nopend', 'nib'], 'uniq_mitra_lpu_kpc_nopend_nib');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_lpu');
    }
};
