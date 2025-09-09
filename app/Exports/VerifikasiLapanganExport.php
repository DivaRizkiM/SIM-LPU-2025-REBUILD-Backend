<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class VerifikasiLapanganExport
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getSpreadsheet(): Spreadsheet
    {
        $templatePath = public_path('template_excel/template_laporan_verifikasi_lapangan.xlsx');
        $reader = new Xlsx();
        $spreadsheet = $reader->load($templatePath);

        $sheet = $spreadsheet->getActiveSheet();
        $row = 3;
        $no = 1;
        foreach ($this->data as $item) {
            $sheet->setCellValue('A' . $row, $no++); // No.
            $sheet->setCellValue('B' . $row, $item['tanggal']); // Tanggal
                                    // Convert the petugas_list array to a string, each item prefixed with a hyphen and a new line
        // If $item['petugas_list'] is a collection of objects, you can pluck the values you want:
            $petugasList = $item['petugas_list']; // Assuming this is a collection

            // Pluck the names or any other attribute you want to implode, e.g., 'name'
            $petugasListArray = $petugasList->pluck('nama_petugas')->toArray();

            // Now you can implode it
            $petugasListFormatted = implode("\n-", $petugasListArray);
            $petugasListFormatted = '-' . $petugasListFormatted; // Add the first hyphen for the first user

            // Set the cell value and enable text wrapping
            $sheet->setCellValue('C' . $row, $petugasListFormatted); // Petugas List
            $sheet->getStyle('C' . $row)->getAlignment()->setWrapText(true); // Enable text wrapping

            $sheet->setCellValue('D' . $row, $item['kode_pos']); // Kode Pos
            $sheet->setCellValue('E' . $row, $item['provinsi']); // Provinsi
            $sheet->setCellValue('F' . $row, $item['kabupaten']); // Kabupaten
            $sheet->setCellValue('G' . $row, $item['kecamatan']); // Kecamatan
            $sheet->setCellValue('H' . $row, $item['kelurahan']); // Kelurahan
            $sheet->setCellValue('I' . $row, $item['kantor_lpu']); // Kantor LPU
            $sheet->setCellValue('J' . $row, $item['aspek_operasional']); // Aspek Operasional
            $sheet->setCellValue('K' . $row, $item['aspek_sarana']); // Aspek Sarana
            $sheet->setCellValue('L' . $row, $item['aspek_wilayah']); // Aspek Wilayah
            $sheet->setCellValue('M' . $row, $item['aspek_pegawai']); // Aspek Pegawai
            $sheet->setCellValue('N' . $row, $item['nilai_akhir']); // Nilai Akhir
            $sheet->setCellValue('O' . $row, $item['kesimpulan']); // Kesimpulan

            $row++;
        }
        return $spreadsheet;
//         return [
//             AfterSheet::class => function (AfterSheet $event) {
//                 // Load template file
//                 $templatePath = storage_path('app/template_excel/Verifikasi_Lapangan.xlsx');
//                 $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
//                 try {
//                     $spreadsheet = $reader->load($templatePath);
//                 } catch (\Exception $e) {
//                     // Handle template loading error
//                     return; // Exit the event listener if template loading fails
//                 }

//                 // Get active sheet
//                 $sheet = $spreadsheet->getActiveSheet();

//                 // Insert data starting from row 4
//                 $row = 6;
//                 $no = 1;
//                 foreach ($this->data as $item) {
//                     $sheet->setCellValue('A' . $row, $no++); // No.
//                     $sheet->setCellValue('B' . $row, $item['tanggal']); // Tanggal
//                                             // Convert the petugas_list array to a string, each item prefixed with a hyphen and a new line
//              // If $item['petugas_list'] is a collection of objects, you can pluck the values you want:
// $petugasList = $item['petugas_list']; // Assuming this is a collection

// // Pluck the names or any other attribute you want to implode, e.g., 'name'
// $petugasListArray = $petugasList->pluck('nama_petugas')->toArray();

// // Now you can implode it
// $petugasListFormatted = implode("\n-", $petugasListArray);
// $petugasListFormatted = '-' . $petugasListFormatted; // Add the first hyphen for the first user

// // Set the cell value and enable text wrapping
// $sheet->setCellValue('C' . $row, $petugasListFormatted); // Petugas List
// $sheet->getStyle('C' . $row)->getAlignment()->setWrapText(true); // Enable text wrapping

//                     $sheet->setCellValue('D' . $row, $item['kode_pos']); // Kode Pos
//                     $sheet->setCellValue('E' . $row, $item['provinsi']); // Provinsi
//                     $sheet->setCellValue('F' . $row, $item['kabupaten']); // Kabupaten
//                     $sheet->setCellValue('G' . $row, $item['kecamatan']); // Kecamatan
//                     $sheet->setCellValue('H' . $row, $item['kelurahan']); // Kelurahan
//                     $sheet->setCellValue('I' . $row, $item['kantor_lpu']); // Kantor LPU
//                     $sheet->setCellValue('J' . $row, $item['aspek_operasional']); // Aspek Operasional
//                     $sheet->setCellValue('K' . $row, $item['aspek_sarana']); // Aspek Sarana
//                     $sheet->setCellValue('L' . $row, $item['aspek_wilayah']); // Aspek Wilayah
//                     $sheet->setCellValue('M' . $row, $item['aspek_pegawai']); // Aspek Pegawai
//                     $sheet->setCellValue('N' . $row, $item['nilai_akhir']); // Nilai Akhir
//                     $sheet->setCellValue('O' . $row, $item['kesimpulan']); // Kesimpulan


//                     // Optionally, apply formatting to the row (e.g., font, alignment)
//                     for ($col = 'A'; $col !== 'Q'; $col++) {
//                         $style = $sheet->getStyle($col . $row);
//                         $sheet->duplicateStyle($style, $col . $row);
//                         $font = $style->getFont();
//                         $font->setBold(false);
//                     }

//                     $row++;
//                 }

//                 // Save the spreadsheet as an Excel file
//                 $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
//                 header('Content-Type: application/vnd.ms-excel');
//                 header('Content-Disposition: attachment;filename="Verfikasi_Lapangan.xlsx"');
//                 header('Cache-Control: max-age=0');
//                 $writer->save('php://output');
//                 exit;
//             },
//         ];
    }
}

