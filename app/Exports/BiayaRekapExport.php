<?php
namespace App\Exports;

use App\Models\VerifikasiBiayaRutinDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BiayaRekapExport implements FromCollection, WithHeadings
{
    protected $tahun;
    protected $bulan;

    public function __construct($tahun, $bulan)
    {
        $this->tahun = $tahun;
        $this->bulan = $bulan;
    }

    public function collection()
    {
        return VerifikasiBiayaRutinDetail::query()
            ->whereHas('verifikasiBiayaRutin', function ($q) {
                $q->where('tahun', $this->tahun);
            })
            ->where('bulan', $this->bulan)
            ->with('rekeningBiaya')
            ->get()
            ->groupBy(function ($item) {
                return $item->rekeningBiaya->nama ?? 'Tanpa Nama Rekening';
            })
            ->map(function ($group, $namaRekening) {
                return [
                    'Nama Rekening' => $namaRekening,
                    'Total Nilai Pelaporan' => $group->sum('pelaporan'),
                ];
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'Nama Rekening',
            'Total Nilai Pelaporan',
        ];
    }
}