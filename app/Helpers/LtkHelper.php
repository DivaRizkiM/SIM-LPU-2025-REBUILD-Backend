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
    /**
     * In-memory request-level cache to avoid duplicate queries within the same request.
     */
    protected static $memCache = [];

    /**
     * Get value from memory cache, or compute and store it.
     */
    protected static function cached(string $key, callable $callback)
    {
        if (!array_key_exists($key, static::$memCache)) {
            static::$memCache[$key] = $callback();
        }
        return static::$memCache[$key];
    }

    /**
     * Clear in-memory cache (useful after sync/import).
     */
    public static function clearCache()
    {
        static::$memCache = [];
    }

    public static function calculateJoinCost($periode, $tahun, $bulan)
    {
        $bulanPad = str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $tahunStr = (string) $tahun;

        return static::cached("join_cost_{$tahunStr}_{$bulanPad}", function () use ($tahunStr, $bulanPad) {
            $produksiKurir = DB::table('produksi_nasional')->whereIn('produk', self::getLayananKurir())->where('status', 'OUTGOING')->where('tahun', $tahunStr)->where('bulan', $bulanPad)->sum('jml_produksi') ?? 0;
            $meterai = DB::table('produksi_nasional')->where('produk', 'METERAI')->where('tahun', $tahunStr)->where('bulan', $bulanPad)->sum('jml_produksi');
            $meterai = $meterai ? $meterai / 10 : 0;
            $outgoing = DB::table('produksi_nasional')->whereIn('produk', self::getLayananJaskug())->whereNotIn('produk', ['METERAI', 'WESELPOS', 'WESELPOS LN'])->where('status', 'OUTGOING')->where('tahun', $tahunStr)->where('bulan', $bulanPad)->sum('jml_produksi') ?? 0;
            $weselposLN = DB::table('produksi_nasional')->where('produk', 'WESELPOS LN')->whereIn('status', ['INCOMING', 'OUTGOING'])->where('tahun', $tahunStr)->where('bulan', $bulanPad)->sum('jml_produksi') ?? 0;
            $weselpos = DB::table('produksi_nasional')->where('produk', 'WESELPOS')->where('status', 'OUTGOING')->where('tahun', $tahunStr)->where('bulan', $bulanPad)->sum('jml_produksi') ?? 0;
            $produkJaskug = $meterai + $outgoing + $weselposLN + $weselpos;
            return ['produksi_kurir' => $produksiKurir, 'produksi_jaskug' => $produkJaskug, 'total_produksi' => $produksiKurir + $produkJaskug, 'detail_jaskug' => ['meterai' => $meterai, 'outgoing' => $outgoing, 'weselpos_ln' => $weselposLN, 'weselpos' => $weselpos]];
        });
    }
    public static function calculateCommonCost($periode, $tahun, $bulan)
    {
        $bulanPad = str_pad($bulan, 2, '0', STR_PAD_LEFT);

        return static::cached("common_cost_{$tahun}_{$bulanPad}", function () use ($tahun, $bulanPad) {
            try {
                $kodeRekeningPendapatanLTK = ['4102010001', '4102010002', '4102010003', '4102010004', '4102010005', '4102010006', '4102010007', '4202000001', '4102020001', '4103010002'];
                $kodeRekeningPendapatanKurir = ['4101010001', '4101010002', '4101010003', '4201000001', '4201000002', '4101020001', '4101020002', '4101020003', '4101020004', '4101020005', '4101020006', '4101030001', '4101030002', '4101030003', '4101030004', '4101030005'];
                $pendapatanKurir = DB::table('verifikasi_ltk')->whereIn('kode_rekening', $kodeRekeningPendapatanKurir)->where('kategori_cost', 'PENDAPATAN')->where('tahun', $tahun)->where('bulan', $bulanPad)->sum('mtd_akuntansi') ?? 0;
                $pendapatanLTK = DB::table('verifikasi_ltk')->whereIn('kode_rekening', $kodeRekeningPendapatanLTK)->where('kategori_cost', 'PENDAPATAN')->where('tahun', $tahun)->where('bulan', $bulanPad)->sum('mtd_akuntansi') ?? 0;
                return ['pendapatan_kurir' => $pendapatanKurir, 'pendapatan_ltk' => $pendapatanLTK, 'total_pendapatan' => $pendapatanKurir + $pendapatanLTK,];
            } catch (\Exception $e) {
                return ['produksi_kurir' => 0, 'pendapatan_jaskug' => 0, 'total_pendapatan' => 0,];
            }
        });
    }
    public static function getJaskugKcpLpuNasional($tahun, $bulan)
    {
        $bulanPad = str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $tahunStr = (string) $tahun;

        return static::cached("jaskug_kcp_lpu_{$tahunStr}_{$bulanPad}", function () use ($tahunStr, $bulanPad) {
            return ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->whereIn('produksi_detail.keterangan', self::getLayananJaskug())
                ->where('produksi.bulan', $bulanPad)
                ->where('produksi.tahun_anggaran', $tahunStr)
                ->sum('produksi_detail.pelaporan') ?? 0;
        });
    }
    public static function getJaskugNasional($tahun, $bulan)
    {
        $bulanPad = str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $tahunStr = (string) $tahun;

        return static::cached("jaskug_nasional_{$tahunStr}_{$bulanPad}", function () use ($tahunStr, $bulanPad) {
            return ProduksiNasional::whereIn('produk', self::getLayananJaskug())
                ->where('bulan', $bulanPad)
                ->where('tahun', $tahunStr)
                ->sum('jml_produksi') ?? 0;
        });
    }
    public static function calculateVerifikasiPerKcp($verifikasiAkuntansi)
    {
        try {
            $totalKcp = static::cached('total_kcp_lpu', function () {
                return Kprk::sum('jumlah_kpc_lpu') ?? 1;
            });
            return $totalKcp > 0 ? ($verifikasiAkuntansi / $totalKcp) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    public static function calculateProporsiByCategory($mtdLTKVerifikasi, $kategoriCost, $tahun, $bulan)
    {
        $proporsiData = [];
        try {
            // Sub-functions (calculateJoinCost, calculateCommonCost, etc.) are
            // individually cached via static::cached(), so repeated calls are free.
            switch (strtoupper($kategoriCost)) {
                case 'FULLCOST':
                case 'FULL':
                case '100%':
                    $rumusFase1 = 1.0;
                    $proporsiBiaya = $mtdLTKVerifikasi * $rumusFase1;
                    $proporsiData = ['keterangan' => $kategoriCost, 'rumus_fase_1' => '100% MTD LTK Verifikasi', 'proporsi_rumus_fase_1_raw' => $rumusFase1, 'proporsi_rumus_fase_1' => '100', 'hasil_perhitungan_fase_1_raw' => $proporsiBiaya, 'hasil_perhitungan_fase_1' => number_format($proporsiBiaya, 0, ',', '.')];
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
                    $proporsiData = ['keterangan' => $kategoriCost, 'rumus_fase_1' => 'MTD LTK Verifikasi * Produksi Produk Jaskug / (Produksi Produk Jaskug + Produksi Produk Kurir)', 'proporsi_rumus_fase_1_raw' => $rumusFase1, 'proporsi_rumus_fase_1' => number_format($rumusFase1 * 100, 2, ',', '.'), 'total_produksi_jaskug_nasional' => $produksiJaskug, 'total_produksi' => $totalProduksi, 'hasil_perhitungan_fase_1_raw' => $proporsiBiaya, 'hasil_perhitungan_fase_1' => number_format($proporsiBiaya, 0, ',', '.')];
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
                    $proporsiData = ['keterangan' => $kategoriCost, 'rumus_fase_1' => 'MTD LTK Verifikasi * Pendapatan Produk Jaskug / (Pendapatan Produk Jaskug + Pendapatan Produk Kurir)', 'proporsi_rumus_fase_1_raw' => $rumusFase1, 'proporsi_rumus_fase_1' => number_format($rumusFase1 * 100, 2, ',', '.'), 'pendapatan_ltk' => $pendapatanLTK, 'pendapatan_kurir' => $pendapatanKurir, 'total_pendapatan' => $totalPendapatan, 'hasil_perhitungan_fase_1_raw' => $proporsiBiaya, 'hasil_perhitungan_fase_1' => number_format($proporsiBiaya, 0, ',', '.')];
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
        return static::cached('layanan_kurir', function () {
            return LayananKurir::pluck('nama')->toArray();
        });
    }
    public static function getLayananJaskug()
    {
        return static::cached('layanan_jaskug', function () {
            return LayananJasaKeuangan::pluck('nama')->toArray();
        });
    }
    public static function calculateFase2($tahunStr, $bulan)
    {
        // ===============================
        // LTK (Tanpa Materai)
        // ===============================
        $ltk = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
            ->where('produksi_detail.kategori_produksi', 'LAYANAN BERBASIS FEE')
            ->whereNotIn('produksi_detail.kode_rekening', ['2101010006'])
            ->where('produksi.tahun_anggaran', $tahunStr)
            ->where('produksi.bulan', $bulan)
            ->sum('produksi_detail.bilangan');

        // ===============================
        // Materai (HARUS DIFILTER JUGA KATEGORI)
        // ===============================
        $materaiLtk = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
            ->where('produksi_detail.kategori_produksi', 'LAYANAN BERBASIS FEE') // 🔥 FIX DI SINI
            ->where('produksi_detail.kode_rekening', '2101010006')
            ->where('produksi.tahun_anggaran', $tahunStr)
            ->where('produksi.bulan', $bulan)
            ->sum('produksi_detail.bilangan');

        $materaiLtk = $materaiLtk ? $materaiLtk / 10 : 0;

        // ===============================
        // TOTAL LTK KANTOR LPU
        // ===============================
        $totalProduksiLtkKantorLpu = $ltk + $materaiLtk;

        return [
            'ltk' => $ltk,
            'materai_ltk' => $materaiLtk,
            'total_produksi_ltk_kantor_lpu' => $totalProduksiLtkKantorLpu,
        ];
    }
    public static function calculateFase3($hasilFase2, $tahun, $bulan, $id_kcp)
    {
        $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $tahunStr = (string) $tahun;

        // Single JOIN query: KCP-specific + Total (replaces 2 EXISTS subqueries)
        $data = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
            ->where('produksi_detail.kategori_produksi', 'LAYANAN BERBASIS FEE')
            ->where('produksi.tahun_anggaran', $tahunStr)
            ->where('produksi.bulan', $bulan)
            ->selectRaw("
                SUM(CASE WHEN produksi.id_kpc = ? THEN produksi_detail.bilangan ELSE 0 END) as kcp,
                SUM(produksi_detail.bilangan) as total
            ", [$id_kcp])
            ->first();

        $produksiKcpLpuA = (float) ($data->kcp ?? 0);
        $produksiLtkKantorLpu = (float) ($data->total ?? 0);

        $rasio = ($produksiLtkKantorLpu > 0)
            ? ($produksiKcpLpuA / $produksiLtkKantorLpu)
            : 0;

        $hasilFase3 = $rasio * $hasilFase2;

        return [
            'produksi_kcp_lpu_a' => $produksiKcpLpuA,
            'total_produksi_ltk_kantor_lpu' => $produksiLtkKantorLpu,
            'rasio' => $rasio,
            'hasil_fase_3' => $hasilFase3
        ];
    }
}
