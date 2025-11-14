<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MTDBiayaPOSFromMTDBiayaHasilSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            DB::table('verifikasi_ltk')->update([
                'mtd_biaya_pos' => DB::raw('mtd_biaya_hasil')
            ]);
        });

        if (app()->runningInConsole() && isset($this->command)) {
            $this->command->info('verifikasi_ltk: kolom mtd_biaya_pos diisi ulang dari mtd_biaya_hasil');
        }
    }
}
