<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SinkronisasiVerifikasiSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Memulai sinkronisasi nilai verifikasi...');

        // Update verifikasi_biaya_rutin_detail
        $updatedVBRD = DB::table('verifikasi_biaya_rutin_detail')
            ->whereRaw('ROUND(pelaporan) = ROUND(verifikasi)')
            ->whereRaw('pelaporan != verifikasi')
            ->update(['verifikasi' => DB::raw('pelaporan')]);
        
        $this->command->info("Verifikasi Biaya Rutin Detail: {$updatedVBRD} record diupdate");

        // Update produksi_detail
        $updatedPD = DB::table('produksi_detail')
            ->whereRaw('ROUND(pelaporan) = ROUND(verifikasi)')
            ->whereRaw('pelaporan != verifikasi')
            ->update(['verifikasi' => DB::raw('pelaporan')]);
        
        $this->command->info("Produksi Detail: {$updatedPD} record diupdate");

        // Update biaya_atribusi_detail
        $updatedBAD = DB::table('biaya_atribusi_detail')
            ->whereRaw('ROUND(pelaporan) = ROUND(verifikasi)')
            ->whereRaw('pelaporan != verifikasi')
            ->update(['verifikasi' => DB::raw('pelaporan')]);
        
        $this->command->info("Biaya Atribusi Detail: {$updatedBAD} record diupdate");

        $total = $updatedVBRD + $updatedPD + $updatedBAD;
        $this->command->info("Total {$total} record berhasil disinkronkan");
    }
}