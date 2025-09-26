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
                return $item->keterangan ?? 'Keterangan Kosong';
            })
            ->map(function ($group, $keterangan) {
                return [
                    'Keterangan' => $keterangan,
                    'Total Nilai Pelaporan' => $group->sum('pelaporan'),
                ];
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'Keterangan',
            'Total Nilai Pelaporan',
        ];
    }
}