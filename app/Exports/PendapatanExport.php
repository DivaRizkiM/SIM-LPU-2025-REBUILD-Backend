<?php

namespace App\Exports;

use App\Models\ProduksiDetail;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class PendapatanExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    protected $tahun;
    protected $bulan;

    public function __construct($tahun, $bulan)
    {
        $this->tahun = $tahun;
        $this->bulan = $bulan;
    }

    public function query()
    {
        return ProduksiDetail::query()
            ->whereHas('produksi', function ($q) {
                $q->where('tahun_anggaran', $this->tahun);
            })
            ->where('nama_bulan', $this->bulan)
            ->with([
                'produksi.regional',
                'produksi.kprk',
                'produksi.kpc',
                'rekeningBiaya',
            ]);
    }

    public function map($item): array
    {
        return [
            $item->produksi->tahun_anggaran ?? '',
            $item->produksi->triwulan ?? '',
            $item->produksi->regional->nama ?? '',
            $item->produksi->kprk->nama ?? '',
            $item->produksi->kpc->nomor_dirian ?? '',
            $item->produksi->kpc->nama ?? '',
            $item->rekeningBiaya->kode_rekening ?? '',
            $item->kategori_produksi ?? '',
            $item->jenis_produksi ?? '',
            $item->nama_bulan ?? '',
            $item->bsu_bruto ?? '',
            $item->verifikasi ?? '',
            $item->catatan_pemeriksa ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            'Tahun Anggaran',
            'Triwulan',
            'Regional',
            'Nama KPRK',
            'Nomor Dirian',
            'Nama KPC',
            'Kode Rekening',
            'Kategori Produksi',
            'Jenis Produksi',
            'Periode Bulan',
            'Nilai Pelaporan',
            'Nilai Verifikasi',
            'Catatan Pemeriksa',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
