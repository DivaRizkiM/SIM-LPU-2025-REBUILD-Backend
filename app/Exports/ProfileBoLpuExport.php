<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ProfileBoLpuExport implements WithEvents
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
                $templatePath = storage_path('app/template_excel/template_laporan_profil_bo_lpu.xlsx');
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
                $row = 3;
                $no = 1;
                foreach ($this->data as $item) {
                    $sheet->setCellValue('A' . $row, $no++);
                    $sheet->setCellValue('B' . $row, $item['triwulan']);
                    $sheet->setCellValue('C' . $row, $item['tahun']);
                    $sheet->setCellValue('D' . $row, $item['kode_dirian']);
                    $sheet->setCellValue('E' . $row, $item['nama_regional']);
                    $sheet->setCellValue('F' . $row, $item['nama_kprk']);
                    $sheet->setCellValue('G' . $row, $item['nama_kpc']);
                    $sheet->setCellValue('H' . $row, $item['alokasi_dana_lpu'] ?? 0);

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
                header('Content-Disposition: attachment;filename="laporan_profile_bo_lpu.xlsx"');
                header('Cache-Control: max-age=0');
                $writer->save('php://output');
                exit;
            },
        ];
    }

}
