<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class LaporanKertasKerjaVerifikasiDetailExport implements WithEvents
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Load template file
                $templatePath = storage_path('app/template_excel/template-laporan_kertas_kerja_verifikasi_detail.xlsx');
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                try {
                    $spreadsheet = $reader->load($templatePath);
                } catch (\Exception $e) {
                    // Handle template loading error (e.g., log the error)
                    return; // Exit the event listener if template loading fails
                }

                // Get active sheet
                $sheet = $spreadsheet->getActiveSheet();

                // Insert data starting from row 4
                $row = 5;
                $no = 1;
                foreach ($this->data as $item) {
                    // Set nomor urut
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

                    // Duplicate styles from the template for the current row
                    for ($col = 'A'; $col !== 'P'; $col++) {
                        $style = $sheet->getStyle($col . $row);
                        $sheet->duplicateStyle($style, $col . $row);
                        $font = $style->getFont();
                        $font->setBold(false); // Set bold to false
                    }

                    $row++;
                }

                // Save the spreadsheet as an Excel file
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="laporan-kertas-kerja-verifikasi-detail.xlsx"');
                header('Cache-Control: max-age=0');
                $writer->save('php://output');
                exit;
            },
        ];
    }

}
