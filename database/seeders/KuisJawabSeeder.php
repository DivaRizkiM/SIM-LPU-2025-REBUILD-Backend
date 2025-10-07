<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KuisJawabSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = database_path('seeders/data/kuis_jawab_kantor.csv');

        if (!file_exists($file)) {
            $this->command->error("CSV not found: {$file}");
            return;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->command->error("Gagal membuka file CSV");
            return;
        }

        $header = null;
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (!$header) {
                $header = $row;
                continue;
            }
            $data = array_combine($header, $row);

            // Sesuaikan mapping sesuai header CSV: id, id_tanya, nama, skor, urut
            $rows[] = [
                'id'       => isset($data['id']) ? (int) $data['id'] : null,
                'id_tanya' => isset($data['id_tanya']) && $data['id_tanya'] !== '' ? (int) $data['id_tanya'] : null,
                'nama'     => $data['nama'] ?? null,
                'skor'     => isset($data['skor']) && $data['skor'] !== '' ? (float) $data['skor'] : 0.0,
                'urut'     => isset($data['urut']) && $data['urut'] !== '' ? (int) $data['urut'] : 0,
            ];
        }
        fclose($handle);

        if (!empty($rows)) {
            // gunakan 'id' sebagai unique key (sesuaikan bila tabel punya pk berbeda)
            DB::table('kuis_jawab_kantor')->upsert($rows, ['id'], ['id_tanya','nama','skor','urut']);
            $this->command->info(count($rows) . " baris diimpor ke kuis_jawab_kantor.");
        } else {
            $this->command->info("Tidak ada data yang diimpor.");
        }
    }
}
