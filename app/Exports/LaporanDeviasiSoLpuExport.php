<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class LaporanDeviasiSoLpuExport
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getSpreadsheet(): Spreadsheet
    {
        $templatePath = public_path('template_excel/template_laporan_deviasi_dana_lpu.xlsx');
        $reader = new Xlsx();
        $spreadsheet = $reader->load($templatePath);

        $sheet = $spreadsheet->getActiveSheet();
        $row = 4;
        $no = 1;
        foreach ($this->data as $item) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item['nomor_dirian']);
            $sheet->setCellValue('C' . $row, $item['nama_kpc']);
            $sheet->setCellValue('D' . $row, $item['sum_alokasi_dana_lpu'] ?? 0);
            $sheet->setCellValue('E' . $row, $item['realisasi'] ?? 0);
            $sheet->setCellValue('F' . $row, $item['deviasi'] ?? 0);
            $row++;
        }
        return $spreadsheet;
        // return [
        //     AfterSheet::class => function (AfterSheet $event) {
        //         // Load template file
        //         $templatePath = storage_path('app/template_excel/template_laporan_deviasi_dana_lpu.xlsx');
        //         $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        //         try {
        //             $spreadsheet = $reader->load($templatePath);
        //         } catch (\Exception $e) {
        //             // Handle template loading error (e.g., log the error)
        //             return; // Exit the event listener if template loading fails
        //         }

        //         // Get active sheet
        //         $sheet = $spreadsheet->getActiveSheet();

        //         // Insert data starting from row 4
        //         $row = 4;
        //         $no = 1;
        //         foreach ($this->data as $item) {
        //             $sheet->setCellValue('A' . $row, $no++);
        //             $sheet->setCellValue('B' . $row, $item['nomor_dirian']);
        //             $sheet->setCellValue('C' . $row, $item['nama_kpc']);
        //             $sheet->setCellValue('D' . $row, $item['sum_alokasi_dana_lpu'] ?? 0);
        //             $sheet->setCellValue('E' . $row, $item['realisasi'] ?? 0);
        //             $sheet->setCellValue('F' . $row, $item['deviasi'] ?? 0);

        //             for ($col = 'A'; $col !== 'K'; $col++) {
        //                 $style = $sheet->getStyle($col . $row);
        //                 $sheet->duplicateStyle($style, $col . $row);
        //                 $font = $style->getFont();
        //                 $font->setBold(false); // Set bold to false
        //             }

        //             $row++;
        //         }

        //         // Save the spreadsheet as an Excel file
        //         $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        //         header('Content-Type: application/vnd.ms-excel');
        //         header('Content-Disposition: attachment;filename="laporan_deviasi_so_lpu.xlsx"');
        //         header('Cache-Control: max-age=0');
        //         $writer->save('php://output');
        //         exit;
        //     },
        // ];
    }

}
