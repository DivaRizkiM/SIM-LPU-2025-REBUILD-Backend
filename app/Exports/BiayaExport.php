<?php
namespace App\Exports;

use App\Models\VerifikasiBiayaRutinDetail;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class BiayaExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
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
        $verifikasiBiayaRutinDetail = VerifikasiBiayaRutinDetail::query()
            ->whereHas('verifikasiBiayaRutin', function ($q) {
                $q->where('tahun', $this->tahun);
            })
            ->where('bulan', $this->bulan);
            
        return $verifikasiBiayaRutinDetail->with(['verifikasiBiayaRutin', 'rekeningBiaya']);
    }
    
    public function map($item): array
    {
        return [
            $item->verifikasiBiayaRutin->tahun ?? '',
            $item->verifikasiBiayaRutin->triwulan ?? '',
            $item->verifikasiBiayaRutin->regional->nama ?? '',
            $item->verifikasiBiayaRutin->kprk->nama ?? '',
            $item->verifikasiBiayaRutin->kpc->nomor_dirian ?? '',
            $item->verifikasiBiayaRutin->kpc->nama ?? '',
            $item->kategori_biaya ?? '',
            $item->bulan ?? '',
            $item->rekeningBiaya->kode_rekening ?? '',
            $item->rekeningBiaya->nama ?? '',
            $item->pelaporan ?? '',
            $item->verifikasi ?? '',
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
            'Kategori Biaya',
            'Periode Bulan',
            'Kode Rekening',
            'Nama Rekening',
            'Pelaporan',
            'Nilai Verifikasi',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}