<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


class LaporanKertasKerjaVerifikasiDetailExport
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getSpreadsheet(): Spreadsheet
    {
        $templatePath = public_path('template_excel/template-laporan_kertas_kerja_verifikasi_detail.xlsx');
        $reader = new Xlsx();
        $spreadsheet = $reader->load($templatePath);

        $sheet = $spreadsheet->getActiveSheet();
        $row = 5;
        $no = 1;
        foreach ($this->data as $item) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item['nama_kpc']);
            $sheet->setCellValue('C' . $row, $item['alokasi_dana_lpu'] ?? '');
            $sheet->setCellValue('D' . $row, $item['pelaporan_kprk_biaya'] ?? 0);
            $sheet->setCellValue('E' . $row, $item['pelaporan_sisa_layanan'] ?? 0);
            $sheet->setCellValue('F' . $row, $item['pelaporan_transfer_pricing'] ?? 0);
            $sheet->setCellValue('G' . $row, $item['total_laporan_kprk'] ?? 0);
            $sheet->setCellValue('H' . $row, $item['hasil_biaya'] ?? 0);
            $sheet->setCellValue('I' . $row, $item['verifikasi_sisa_layanan'] ?? 0);
            $sheet->setCellValue('J' . $row, $item['hasil_transfer_pricing'] ?? 0);
            $sheet->setCellValue('K' . $row, $item['total_hasil_verifikasi'] ?? 0);
            $sheet->setCellValue('L' . $row, $item['deviasi_biaya'] ?? 0);
            $sheet->setCellValue('M' . $row, $item['deviasi_sisa'] ?? 0);
            $sheet->setCellValue('N' . $row, $item['deviasi_transfer'] ?? 0);
            $sheet->setCellValue('O' . $row, $item['deviasi_produksi'] ?? 0);
            $sheet->setCellValue('P' . $row, $item['deviasi_akhir'] ?? 0);
            $row++;
        }
        return $spreadsheet;
    }

}
