<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class LaporanKertasKerjaVerifikasiExport implements WithEvents
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
                $templatePath = storage_path('app/template_excel/template-laporan_kertas_kerja_verifikasi.xlsx');
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
                $row = 4;
                $no = 1;
                foreach ($this->data as $item) {
                    // Set nomor urut
                    $sheet->setCellValue('A' . $row, $no++);

                    // Set nilai untuk kolom nama KPRK, jumlah KPC, dan kolom lainnya
                    $sheet->setCellValue('B' . $row, $item['nama_kprk']);
                    $sheet->setCellValue('C' . $row, $item['jumlah_kpc'] ?? ''); // Tambahkan nilai jumlah KPC, atau kosongkan jika tidak ada
                    $sheet->setCellValue('D' . $row, $item['hasil_pelaporan_biaya'] ?? 0);
                    $sheet->setCellValue('E' . $row, $item['hasil_pelaporan_pendapatan'] ?? 0);
                    $sheet->setCellValue('F' . $row, $item['hasil_verifikasi_biaya'] ?? 0);
                    $sheet->setCellValue('G' . $row, $item['hasil_verifikasi_pendapatan'] ?? 0);
                    $sheet->setCellValue('H' . $row, $item['deviasi_biaya'] ?? 0);
                    $sheet->setCellValue('I' . $row, $item['deviasi_produksi'] ?? 0);
                    $sheet->setCellValue('J' . $row, $item['deviasi_akhir'] ?? 0);
                    // Duplicate styles from the template for the current row
                    for ($col = 'A'; $col !== 'K'; $col++) {
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
                header('Content-Disposition: attachment;filename="laporan-kertas-kerja-verifidddddkasi.xlsx"');
                header('Cache-Control: max-age=0');
                $writer->save('php://output');
                exit;
            },
        ];
    }

}
