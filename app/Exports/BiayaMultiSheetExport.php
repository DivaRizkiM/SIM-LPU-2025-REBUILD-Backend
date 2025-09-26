<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BiayaMultiSheetExport implements WithMultipleSheets
{
    protected $tahun;
    protected $bulan;

    public function __construct($tahun, $bulan)
    {
        $this->tahun = $tahun;
        $this->bulan = $bulan;
    }

    public function sheets(): array
    {
        return [
            // new BiayaExport($this->tahun, $this->bulan),      // Sheet utama
            new BiayaRekapExport($this->tahun, $this->bulan), // Sheet rekap
        ];
    }
}