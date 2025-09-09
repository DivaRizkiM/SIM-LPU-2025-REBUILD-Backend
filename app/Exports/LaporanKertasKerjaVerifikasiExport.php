<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class LaporanKertasKerjaVerifikasiExport
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getSpreadsheet(): Spreadsheet
    {
        $templatePath = public_path('template_excel/template-laporan_kertas_kerja_verifikasi.xlsx');

        $reader = new Xlsx();
        $spreadsheet = $reader->load($templatePath);

        $sheet = $spreadsheet->getActiveSheet();
        $row = 4;
        $no = 1;

        foreach ($this->data as $item) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item['nama_kprk']);
            $sheet->setCellValue('C' . $row, $item['jumlah_kpc'] ?? '');
            $sheet->setCellValue('D' . $row, $item['hasil_pelaporan_biaya'] ?? 0);
            $sheet->setCellValue('E' . $row, $item['hasil_pelaporan_pendapatan'] ?? 0);
            $sheet->setCellValue('F' . $row, $item['hasil_verifikasi_biaya'] ?? 0);
            $sheet->setCellValue('G' . $row, $item['hasil_verifikasi_pendapatan'] ?? 0);
            $sheet->setCellValue('H' . $row, $item['deviasi_biaya'] ?? 0);
            $sheet->setCellValue('I' . $row, $item['deviasi_produksi'] ?? 0);
            $sheet->setCellValue('J' . $row, $item['deviasi_akhir'] ?? 0);
            $row++;
        }

        return $spreadsheet;
    }
}
