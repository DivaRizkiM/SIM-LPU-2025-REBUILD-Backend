<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KuisTanyaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = database_path('seeders/data/kuis_tanya_kantor.csv');

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

            // Mapping sesuai header CSV:
            // id, id_tanya, nama, persen, form_type, form_sort, form_page
            $rows[] = [
                'id'         => isset($data['id']) ? (int) $data['id'] : null,
                'id_tanya'   => isset($data['id_tanya']) && $data['id_tanya'] !== '' ? (int) $data['id_tanya'] : null,
                'nama'       => $data['nama'] ?? null,
                'persen'     => isset($data['persen']) && $data['persen'] !== '' ? (float) $data['persen'] : 0.0,
                'form_type'  => $data['form_type'] ?? null,
                'form_sort'  => isset($data['form_sort']) && $data['form_sort'] !== '' ? (int) $data['form_sort'] : 0,
                'form_page'  => isset($data['form_page']) && $data['form_page'] !== '' ? (int) $data['form_page'] : 0,
            ];
        }
        fclose($handle);

        if (!empty($rows)) {
            // gunakan 'id' sebagai unique key
            DB::table('kuis_tanya_kantor')->upsert($rows, ['id'], ['id_tanya','nama','persen','form_type','form_sort','form_page']);
            $this->command->info(count($rows) . " baris diimpor ke kuis_tanya_kantor.");
        } else {
            $this->command->info("Tidak ada data yang diimpor.");
        }
    }
}
