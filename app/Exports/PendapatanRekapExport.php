<?php

namespace App\Exports;

use App\Models\ProduksiDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PendapatanRekapExport implements FromCollection, WithHeadings
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
        return ProduksiDetail::query()
            ->whereHas('produksi', function ($q) {
                $q->where('tahun_anggaran', $this->tahun);
            })
            ->where('nama_bulan', $this->bulan)
            ->get()
            ->groupBy(function ($item) {
                return $item->nama_rekening ?? 'Tanpa Nama Rekening';
            })
            ->map(function ($group, $namaRekening) {
                return [
                    'Nama Rekening' => $namaRekening,
                    'Total Nilai Pelaporan' => $group->sum('bsu_bruto'),
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