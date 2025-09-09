<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class CakupanWilayahExport implements WithEvents
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
                $templatePath = storage_path('app/template_excel/cakupan_wilayah.xlsx');
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
                    $sheet->setCellValue('A' . $row, $no++);
                    $sheet->setCellValue('B' . $row, $item['nama_provinsi']);
                    $sheet->setCellValue('C' . $row, $item['nama_kabupaten']);
                    $sheet->setCellValue('D' . $row, $item['nama_kecamatan']);
                    $sheet->setCellValue('E' . $row, $item['nama_kelurahan']);
                    $sheet->setCellValue('F' . $row, $item['JNT']);
                    $sheet->setCellValue('G' . $row, $item['SICEPAT']);

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
                header('Content-Disposition: attachment;filename="cakupan_wilayah.xlsx"');
                header('Cache-Control: max-age=0');
                $writer->save('php://output');
                exit;
            },
        ];
    }

}
