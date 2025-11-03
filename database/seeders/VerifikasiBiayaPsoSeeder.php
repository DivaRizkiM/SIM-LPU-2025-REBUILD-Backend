<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VerifikasiBiayaPsoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            DB::table('verifikasi_ltk')->update([
                'verifikasi_pso' => DB::raw('biaya_pso')
            ]);
        });

        if (app()->runningInConsole() && isset($this->command)) {
            $this->command->info('verifikasi_ltk: kolom verifikasi_pso diisi ulang dari biaya_pso');
        }
    }
}
