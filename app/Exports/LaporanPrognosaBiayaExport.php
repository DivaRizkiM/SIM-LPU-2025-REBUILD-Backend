<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class LaporanPrognosaBiayaExport implements WithEvents
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
                $templatePath = storage_path('app/template_excel/template_laporan_biaya.xlsx');
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
                    $sheet->setCellValue('B' . $row, $item['nama_regional']);
                    $sheet->setCellValue('C' . $row, $item['nama_kprk']);
                    $sheet->setCellValue('D' . $row, $item['nomor_dirian']);
                    $sheet->setCellValue('E' . $row, $item['nama_kpc']);
                    $sheet->setCellValue('F' . $row, $item['total_biaya_pegawai'] ?? 0);
                    $sheet->setCellValue('G' . $row, $item['total_biaya_operasi'] ?? 0);
                    $sheet->setCellValue('H' . $row, $item['total_biaya_pemeliharaan'] ?? 0);
                    $sheet->setCellValue('I' . $row, $item['total_biaya_administrasi'] ?? 0);
                    $sheet->setCellValue('J' . $row, $item['total_biaya_penyusutan'] ?? 0);
                    $sheet->setCellValue('K' . $row, $item['jumlah_biaya'] ?? 0);
                    // Duplicate styles from the template for the current row
                    for ($col = 'A'; $col !== 'J'; $col++) {
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
                header('Content-Disposition: attachment;filename="laporan-prognosa-biaya.xlsx"');
                header('Cache-Control: max-age=0');
                $writer->save('php://output');
                exit;
            },
        ];
    }

}
