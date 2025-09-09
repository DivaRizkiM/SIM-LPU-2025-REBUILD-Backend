<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class LaporanPrognosaPendapatanExport
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getSpreadsheet(): Spreadsheet
    {
        $templatePath = public_path('template_excel/template_laporan_pendapatan.xlsx');

        $reader = new Xlsx();
        $spreadsheet = $reader->load($templatePath);

        $sheet = $spreadsheet->getActiveSheet();
        $row = 3;
        $no = 1;
        foreach ($this->data as $item) {
            // Set nomor urut
            $sheet->setCellValue('A' . $row, $no++);

            $sheet->setCellValue('B' . $row, $item['nama_regional']);
            $sheet->setCellValue('C' . $row, $item['nama_kprk']);
            $sheet->setCellValue('D' . $row, $item['nomor_dirian']);
            $sheet->setCellValue('E' . $row, $item['nama_kpc']);
            $sheet->setCellValue('F' . $row, $item['total_lpu'] ?? 0);
            $sheet->setCellValue('G' . $row, $item['total_lpk'] ?? 0);
            $sheet->setCellValue('H' . $row, $item['total_lbf'] ?? 0);
            $sheet->setCellValue('I' . $row, $item['jumlah_pendapatan'] ?? 0);

            for ($col = 'A'; $col !== 'J'; $col++) {
                $style = $sheet->getStyle($col . $row);
                $sheet->duplicateStyle($style, $col . $row);
                $font = $style->getFont();
                $font->setBold(false); // Set bold to false
            }

            $row++;
        }
        return $spreadsheet;
        // return [
        //     AfterSheet::class => function (AfterSheet $event) {
        //         // Load template file
        //         $templatePath = storage_path('app/template_excel/template_laporan_pendapatan.xlsx');
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
        //         $row = 3;
        //         $no = 1;
        //         foreach ($this->data as $item) {
        //             // Set nomor urut
        //             $sheet->setCellValue('A' . $row, $no++);

        //             $sheet->setCellValue('B' . $row, $item['nama_regional']);
        //             $sheet->setCellValue('C' . $row, $item['nama_kprk']);
        //             $sheet->setCellValue('D' . $row, $item['nomor_dirian']);
        //             $sheet->setCellValue('E' . $row, $item['nama_kpc']);
        //             $sheet->setCellValue('F' . $row, $item['total_lpu'] ?? 0);
        //             $sheet->setCellValue('G' . $row, $item['total_lpk'] ?? 0);
        //             $sheet->setCellValue('H' . $row, $item['total_lbf'] ?? 0);
        //             $sheet->setCellValue('I' . $row, $item['jumlah_pendapatan'] ?? 0);

        //             for ($col = 'A'; $col !== 'J'; $col++) {
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
        //         header('Content-Disposition: attachment;filename="laporan-progssssssnosa-pendapatan.xlsx"');
        //         header('Cache-Control: max-age=0');
        //         $writer->save('php://output');
        //         exit;
        //     },
        // ];
    }

}
