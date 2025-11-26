<?php

namespace App\Helpers;

use App\Models\Kprk;
use App\Models\LayananJasaKeuangan;
use App\Models\LayananKurir;
use App\Models\ProduksiDetail;
use App\Models\ProduksiNasional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LtkHelper
{
    public static function calculateJoinCost($periode, $tahun, $bulan)
    {
        $produksiKurir = DB::table('produksi_nasional')
            ->whereIn('produk', self::getLayananKurir())
            ->where('status', 'OUTGOING')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        $meterai = DB::table('produksi_nasional')
            ->where('produk', 'METERAI')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi');
        $meterai = $meterai ? $meterai / 10 : 0;

        $outgoing = DB::table('produksi_nasional')
            ->whereIn('produk', self::getLayananJaskug())
            ->whereNotIn('produk', ['METERAI', 'WESELPOS', 'WESELPOS LN'])
            ->where('status', 'OUTGOING')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        $weselposLN = DB::table('produksi_nasional')
            ->where('produk', 'WESELPOS LN')
            ->whereIn('status', ['INCOMING', 'OUTGOING'])
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        $weselpos = DB::table('produksi_nasional')
            ->where('produk', 'WESELPOS')
            ->where('status', 'OUTGOING')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        $produkJaskug = $meterai + $outgoing + $weselposLN + $weselpos;

        return [
            'produksi_kurir' => $produksiKurir,
            'produksi_jaskug' => $produkJaskug,
            'total_produksi' => $produksiKurir + $produkJaskug,
            'detail_jaskug' => [
                'meterai' => $meterai,
                'outgoing' => $outgoing,
                'weselpos_ln' => $weselposLN,
                'weselpos' => $weselpos
            ]
        ];
    }

    public static function calculateCommonCost($periode, $tahun, $bulan)
    {
        try {
            $kodeRekeningPendapatanLTK = [
                '4102010001',
                '4102010002',
                '4102010003',
                '4102010004',
                '4102010005',
                '4102010006',
                '4102010007',
                '4202000001',
                '4102020001',
                '4103010002'
            ];

            $kodeRekeningPendapatanKurir = [
                '4101010001',
                '4101010002',
                '4101010003',
                '4201000001',
                '4201000002',
                '4101020001',
                '4101020002',
                '4101020003',
                '4101020004',
                '4101020005',
                '4101020006',
                '4101030001',
                '4101030002',
                '4101030003',
                '4101030004',
                '4101030005'
            ];

            $pendapatanKurir = DB::table('verifikasi_ltk')
                ->whereIn('kode_rekening', $kodeRekeningPendapatanKurir)
                ->where('kategori_cost', 'PENDAPATAN')
                ->where('tahun', $tahun)
                ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
                ->sum('mtd_akuntansi') ?? 0;

            $pendapatanLTK = DB::table('verifikasi_ltk')
                ->whereIn('kode_rekening', $kodeRekeningPendapatanLTK)
                ->where('kategori_cost', 'PENDAPATAN')
                ->where('tahun', $tahun)
                ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
                ->sum('mtd_akuntansi') ?? 0;

            return [
                'pendapatan_kurir' => $pendapatanKurir,
                'pendapatan_ltk' => $pendapatanLTK,
                'total_pendapatan' => $pendapatanKurir + $pendapatanLTK,
            ];
        } catch (\Exception $e) {
            return [
                'produksi_kurir' => 0,
                'pendapatan_jaskug' => 0,
                'total_pendapatan' => 0,
            ];
        }
    }

    public static function getJaskugKcpLpuNasional($tahun, $bulan)
    {
        $jaskugKcpLpu = ProduksiDetail::whereIn('keterangan', self::getLayananJaskug())
            ->whereHas('produksi', function ($query) use ($bulan, $tahun) {
                $query->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
                    ->where('tahun_anggaran', (string) $tahun);
            })
            ->sum('pelaporan') ?? 0;
        return $jaskugKcpLpu;
    }

    public static function getJaskugNasional($tahun, $bulan)
    {
        $jaskugKcpLpu = ProduksiNasional::whereIn('produk', self::getLayananJaskug())
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->where('tahun', (string) $tahun)
            ->sum('jml_produksi') ?? 0;
        return $jaskugKcpLpu;
    }

    public static function calculateVerifikasiPerKcp($verifikasiAkuntansi)
    {
        try {
            $totalKcp = Kprk::sum('jumlah_kpc_lpu') ?? 1;
            return $totalKcp > 0 ? ($verifikasiAkuntansi / $totalKcp) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function calculateProporsiByCategory($mtdLTKVerifikasi, $kategoriCost, $tahun, $bulan)
    {
        $proporsiData = [];

        try {
            $produksiJaskugKCPLpuNasional = self::getJaskugKcpLpuNasional($tahun, $bulan);
            $produksiJaskugNasional = self::getJaskugNasional($tahun, $bulan);
            $totalKcpLPU = Kprk::sum('jumlah_kpc_lpu') ?? 1;

            switch (strtoupper($kategoriCost)) {
                case 'FULLCOST':
                case 'FULL':
                case '100%':
                    $rumusFase1 = 1.0;
                    $proporsiBiaya = $mtdLTKVerifikasi * $rumusFase1;

                    $proporsiData = [
                        'keterangan' => $kategoriCost,
                        'rumus_fase_1' => '100% MTD LTK Verifikasi',
                        'proporsi_rumus_fase_1_raw' => $rumusFase1,
                        'proporsi_rumus_fase_1' => '100',
                        'hasil_perhitungan_fase_1_raw' => $proporsiBiaya,
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiaya, 0, ',', '.')
                    ];
                    break;

                case 'JOINTCOST':
                case 'JOIN':
                case 'JOIN COST':
                    $joinCost = self::calculateJoinCost('', $tahun, $bulan);
                    $produksiJaskug = $joinCost['produksi_jaskug'] ?? 0;
                    $produksiKurir = $joinCost['produksi_kurir'] ?? 0;
                    $totalProduksi = $produksiJaskug + $produksiKurir;

                    $rumusFase1 = $totalProduksi > 0 ? ($produksiJaskug / $totalProduksi) : 0.0;
                    $proporsiBiaya = $mtdLTKVerifikasi * $rumusFase1;

                    $proporsiData = [
                        'keterangan' => $kategoriCost,
                        'rumus_fase_1' => 'MTD LTK Verifikasi * Produksi Produk Jaskug / (Produksi Produk Jaskug + Produksi Produk Kurir)',
                        'proporsi_rumus_fase_1_raw' => $rumusFase1,
                        'proporsi_rumus_fase_1' => number_format($rumusFase1 * 100, 2, ',', '.'),
                        'total_produksi_jaskug_nasional' => $produksiJaskug,
                        'total_produksi' => $totalProduksi,
                        'hasil_perhitungan_fase_1_raw' => $proporsiBiaya,
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiaya, 0, ',', '.')
                    ];
                    break;

                case 'COMMONCOST':
                case 'COMMON':
                case 'COMMON COST':
                    $commonCost = self::calculateCommonCost('', $tahun, $bulan);
                    $pendapatanLTK = $commonCost['pendapatan_ltk'] ?? 0;
                    $pendapatanKurir = $commonCost['pendapatan_kurir'] ?? 0;
                    $totalPendapatan = $pendapatanLTK + $pendapatanKurir;

                    $rumusFase1 = $totalPendapatan > 0 ? ($pendapatanLTK / $totalPendapatan) : 0.0;
                    $proporsiBiaya = $mtdLTKVerifikasi * $rumusFase1;

                    $proporsiData = [
                        'keterangan' => $kategoriCost,
                        'rumus_fase_1' => 'MTD LTK Verifikasi * Pendapatan Produk Jaskug / (Pendapatan Produk Jaskug + Pendapatan Produk Kurir)',
                        'proporsi_rumus_fase_1_raw' => $rumusFase1,
                        'proporsi_rumus_fase_1' => number_format($rumusFase1 * 100, 2, ',', '.'),
                        'pendapatan_ltk' => $pendapatanLTK,
                        'pendapatan_kurir' => $pendapatanKurir,
                        'total_pendapatan' => $totalPendapatan,
                        'hasil_perhitungan_fase_1_raw' => $proporsiBiaya,
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiaya, 0, ',', '.')
                    ];
                    break;

                default:
                    throw new \Exception("Invalid kategori_cost: {$kategoriCost}");
            }
        } catch (\Exception $e) {
            Log::error("Error in calculateProporsiByCategory: " . $e->getMessage());
            throw $e;
        }

        return $proporsiData;
    }

    public static function getLayananKurir()
    {
        return LayananKurir::select('nama')->get()->pluck('nama')->toArray();
    }

    public static function getLayananJaskug()
    {
        return LayananJasaKeuangan::select('nama')->get()->pluck('nama')->toArray();
    }

    public static function calculateFase2($grandTotalFase1, $tahun, $bulan)
    {
        $ltk = ProduksiDetail::where('kategori_produksi', 'LAYANAN BERBASIS FEE')
            ->whereNotIn('kode_rekening', ['2101010006'])
            ->whereHas('produksi', function ($query) use ($tahun) {
                $query->where('tahun_anggaran', (string)$tahun);
            })
            ->where('nama_bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('bilangan');

        $matraiLTK = ProduksiDetail::where('kode_rekening', '2101010006')
            ->whereHas('produksi', function ($query) use ($tahun) {
                $query->where('tahun_anggaran', (string)$tahun);
            })
            ->where('nama_bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('bilangan');
        $matraiLTK = $matraiLTK ? $matraiLTK / 10 : 0;

        $totalProduksiLtkKantorLpu = $ltk + $matraiLTK;

        $meterai = DB::table('produksi_nasional')
            ->where('produk', 'METERAI')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi');
        $meterai = $meterai ? $meterai / 10 : 0;

        $outgoing = DB::table('produksi_nasional')
            ->whereIn('produk', self::getLayananJaskug())
            ->whereNotIn('produk', ['METERAI', 'WESELPOS', 'WESELPOS LN'])
            ->where('status', 'OUTGOING')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        $weselposLN = DB::table('produksi_nasional')
            ->where('produk', 'WESELPOS LN')
            ->whereIn('status', ['INCOMING', 'OUTGOING'])
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        $weselpos = DB::table('produksi_nasional')
            ->where('produk', 'WESELPOS')
            ->where('status', 'OUTGOING')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        $produksiJaskugNasional = $meterai + $outgoing + $weselposLN + $weselpos;

        $rasio = ($produksiJaskugNasional > 0) ? ($totalProduksiLtkKantorLpu / $produksiJaskugNasional) : 0;
        $hasilFase2 = $rasio * $grandTotalFase1;
        $data = [
            'total_produksi_ltk_kantor_lpu_prod_materai_dibagi_10' => $totalProduksiLtkKantorLpu,
            'produksi_jaskug_nasional' => $produksiJaskugNasional,
            'rasio' => $rasio,
            'hasil_fase_2' => $hasilFase2
        ];
        return $data;
    }

    public static function calculateFase3($hasilFase2, $tahun, $bulan, $id_kcp)
    {
        $produksiKcpLpuA = ProduksiDetail::where('kategori_produksi', 'LAYANAN BERBASIS FEE')
            ->whereHas('produksi', function ($query) use ($tahun, $id_kcp) {
                $query->where('tahun_anggaran', (string)$tahun)
                    ->where('id_kpc', $id_kcp);
            })
            ->where('nama_bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('bilangan');

        $produksiLtkKantorLpu = ProduksiDetail::where('kategori_produksi', 'LAYANAN BERBASIS FEE')
            ->whereHas('produksi', function ($query) use ($tahun) {
                $query->where('tahun_anggaran', (string)$tahun);
            })
            ->where('nama_bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('bilangan');

        $rasio = ($produksiLtkKantorLpu > 0) ? ($produksiKcpLpuA / $produksiLtkKantorLpu) : 0;
        $hasilFase3 = $rasio * $hasilFase2;

        $data = [
            'produksi_kcp_lpu_a' => $produksiKcpLpuA,
            'total_produksi_ltk_kantor_lpu' => $produksiLtkKantorLpu,
            'rasio' => $rasio,
            'hasil_fase_3' => $hasilFase3
        ];

        return $data;
    }
}
