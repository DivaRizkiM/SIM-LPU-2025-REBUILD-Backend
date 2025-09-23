<?php

namespace App\Http\Controllers;

use App\Models\Kprk;
use App\Models\LayananJasaKeuangan;
use App\Models\LayananKurir;
use App\Models\LockVerifikasi;
use App\Models\ProduksiDetail;
use App\Models\ProduksiNasional;
use App\Models\Status;
use App\Models\UserLog;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\VerifikasiLtk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LtkController extends Controller
{
    public function getPerTahun(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'bulan' => 'nullable|numeric',
                'status' => 'nullable|string|in:7,9',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $tahun = request()->get('tahun', '');
            $bulan = request()->get('bulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "bulan ASC";
            $orderMappings = [
                'bulanASC' => 'verifikasi_ltk.bulan ASC',
                'bulanDESC' => 'verifikasi_ltk.bulan DESC',
                'tahunASC' => 'verifikasi_ltk.tahun ASC',
                'tahunDESC' => 'verifikasi_ltk.tahun DESC',
            ];

            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $verifikasiLtkQuery = VerifikasiLtk::orderByRaw($order)
                ->select('verifikasi_ltk.id', 'verifikasi_ltk.keterangan', 'verifikasi_ltk.id_status',  'rekening_biaya.nama as nama_rekening', 'verifikasi_ltk.kode_rekening', 'verifikasi_ltk.mtd_akuntansi', 'verifikasi_ltk.verifikasi_akuntansi', 'verifikasi_ltk.biaya_pso',  'verifikasi_ltk.verifikasi_pso', 'verifikasi_ltk.mtd_biaya_pos as mtd_biaya', 'verifikasi_ltk.mtd_biaya_hasil', 'verifikasi_ltk.proporsi_rumus', 'verifikasi_ltk.verifikasi_proporsi')
                ->join('rekening_biaya', 'verifikasi_ltk.kode_rekening', '=', 'rekening_biaya.id')->whereNot('kategori_cost', 'PENDAPATAN');
            $total_data = $verifikasiLtkQuery->count();
            if ($tahun !== '') {
                $verifikasiLtkQuery->where('verifikasi_ltk.tahun', $tahun);
            }

            if ($bulan !== '') {
                $verifikasiLtkQuery->where('verifikasi_ltk.bulan', $bulan);
            }
            if ($status !== '') {
                $verifikasiLtkQuery->where('verifikasi_ltk.id_status', $status);
            }

            $verifikasiLtk = $verifikasiLtkQuery
                ->offset($offset)
                ->limit($limit)->get();
            $verifikasiLtk = $verifikasiLtk->map(function ($verifikasiLtk) {
                $verifikasiLtk->nominal = (int) $verifikasiLtk->nominal;
                $verifikasiLtk->proporsi_rumus = (float) $verifikasiLtk->proporsi_rumus ?? "0.00";
                $verifikasiLtk->verifikasi_pso = (float) $verifikasiLtk->verifikasi_pso ?? "0.00";
                $verifikasiLtk->verifikasi_akuntansi = (float) $verifikasiLtk->verifikasi_akuntansi ?? "0.00";
                $verifikasiLtk->verifikasi_proporsi = (float) $verifikasiLtk->verifikasi_proporsi ?? "0.00";
                $verifikasiLtk->mtd_biaya = (float) $verifikasiLtk->mtd_biaya ?? "0.00";
                $verifikasiLtk->proporsi_rumus = $verifikasiLtk->keterangan;
                return $verifikasiLtk;
            });
            $grand_total = $verifikasiLtk->sum('mtd_akuntansi');
            $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');

            foreach ($verifikasiLtk as $item) {
                if ($item->id_status == 9) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
                $isLock = LockVerifikasi::where('tahun', $item->tahun)->where('bulan', $item->bulan)->first();

                $isLockStatus = false;
                if ($isLock) {
                    $isLockStatus = $isLock->status;
                }
                $item->isLock = $isLockStatus;
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'grand_total' => $grand_total,
                'data' => $verifikasiLtk,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }


    private function calculateJoinCost($periode, $tahun, $bulan)
    {
        // 1. Produk Kurir - jumlah produksi layanan kurir dengan status OUTGOING
        $produksiKurir = DB::table('produksi_nasional')
            ->whereIn('produk', $this->getLayananKurir())
            ->where('status', 'OUTGOING')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        // 2. Produk Jaskug calculations
        // a. Meterai (divided by 10)
        $meterai = DB::table('produksi_nasional')
            ->where('produk', 'METERAI')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi');
        $meterai = $meterai ? $meterai / 10 : 0;

        // b. Outgoing (layanan jaskug except meterai, weselpos, weselpos ln)
        $outgoing = DB::table('produksi_nasional')
            ->whereIn('produk', $this->getLayananJaskug())
            ->whereNotIn('produk', ['METERAI', 'WESELPOS', 'WESELPOS LN'])
            ->where('status', 'OUTGOING')
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        // c. Weselpos LN (incoming + outgoing)
        $weselposLN = DB::table('produksi_nasional')
            ->where('produk', 'WESELPOS LN')
            ->whereIn('status', ['INCOMING', 'OUTGOING'])
            ->where('tahun', (string)$tahun)
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->sum('jml_produksi') ?? 0;

        // d. Weselpos (OUTGOING only)
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

    private function calculateCommonCost($periode, $tahun, $bulan)
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

    private function getJaskugKcpLpuNasional($tahun, $bulan)
    {
        $jaskugKcpLpu = ProduksiDetail::whereIn('keterangan', $this->getLayananJaskug())
            ->whereHas('produksi', function ($query) use ($bulan, $tahun) {
                $query->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
                    ->where('tahun_anggaran', (string) $tahun);
            })
            ->sum('pelaporan') ?? 0;
        return $jaskugKcpLpu;
    }

    private function getJaskugNasional($tahun, $bulan)
    {
        $jaskugKcpLpu = ProduksiNasional::whereIn('produk', $this->getLayananJaskug())
            ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
            ->where('tahun', (string) $tahun)
            ->sum('jml_produksi') ?? 0;
        return $jaskugKcpLpu;
    }

    private function calculateVerifikasiPerKcp($verifikasiAkuntansi)
    {
        try {
            $totalKcp = Kprk::sum('jumlah_kpc_lpu') ?? 1;
            return $totalKcp > 0 ? ($verifikasiAkuntansi / $totalKcp) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }


    private function calculateProporsiByCategory($mtdBiayaLtk, $kategoriCost, $biayaPso, $tahun, $bulan)
    {
        $proporsiData = [];

        try {
            $produksiJaskugKCPLpuNasional = $this->getJaskugKcpLpuNasional($tahun, $bulan);
            $produksiJaskugNasional = $this->getJaskugNasional($tahun, $bulan);
            $totalKcpLPU = Kprk::sum('jumlah_kpc_lpu') ?? 1;

            switch (strtoupper($kategoriCost)) {
                case 'FULLCOST':
                case 'FULL':
                case '100%':
                    $proporsiBiayaJaskugNasional = $biayaPso * 1.0; // 100%

                    $proporsiData = [
                        'keterangan' => $kategoriCost,
                        'rumus_fase_1' => '100% (Proporsi Tetap)',
                        'proporsi_rumus_fase_1' => '100',
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiayaJaskugNasional, 0, ',', '.')
                    ];
                    break;

                case 'JOINTCOST':
                case 'JOIN':
                case 'JOIN COST':
                    $joinCost = $this->calculateJoinCost('', $tahun, $bulan);
                    $produksiJaskug = $joinCost['produksi_jaskug'] ?? 0;
                    $produksiKurir = $joinCost['produksi_kurir'] ?? 0;
                    $totalProduksi = $produksiJaskug + $produksiKurir;

                    // FASE 1: Joint Cost allocation based on production ratio
                    $rumusFase1 = $totalProduksi > 0 ? ($produksiJaskug / $totalProduksi) : 0;
                    $proporsiBiayaJaskugNasional = $biayaPso * $rumusFase1;

                    $proporsiData = [
                        'keterangan' => $kategoriCost,
                        'rumus_fase_1' => 'Biaya Pso * Produksi Produk Jaskug / (Produksi Produk Jaskug + Produksi Produk Kurir)',
                        'proporsi_rumus_fase_1' => number_format($rumusFase1 * 100, 2, ',', '.'),
                        'total_produksi_jaskug_nasional' => number_format($produksiJaskug, 0, ',', '.'),
                        'total_produksi' => number_format($totalProduksi, 0, ',', '.'),
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiayaJaskugNasional, 0, ',', '.')
                    ];
                    break;

                case 'COMMONCOST':
                case 'COMMON':
                case 'COMMON COST':
                    $commonCost = $this->calculateCommonCost('', $tahun, $bulan);
                    $pendapatanLTK = $commonCost['pendapatan_ltk'] ?? 0;
                    $pendapatanKurir = $commonCost['pendapatan_kurir'] ?? 0;
                    $totalPendapatan = $pendapatanLTK + $pendapatanKurir;

                    // FASE 1: Common Cost allocation based on revenue ratio
                    $rumusFase1 = $totalPendapatan > 0 ? ($pendapatanLTK / $totalPendapatan) : 0;
                    $proporsiBiayaJaskugNasional = $biayaPso * $rumusFase1;

                    $proporsiData = [
                        'keterangan' => $kategoriCost,
                        'rumus_fase_1' => 'Biaya Pso * Pendapatan Produk Jaskug / (Pendapatan Produk Jaskug + Pendapatan Produk Kurir)',
                        'proporsi_rumus_fase_1' => number_format($rumusFase1 * 100, 2, ',', '.'),
                        'pendapatan_ltk' => number_format($pendapatanLTK, 0, ',', '.'),
                        'pendapatan_kurir' => number_format($pendapatanKurir, 0, ',', '.'),
                        'total_pendapatan' => number_format($totalPendapatan, 0, ',', '.'),
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiayaJaskugNasional, 0, ',', '.')
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

    private function getLayananKurir()
    {
        return LayananKurir::select('nama')->get()->pluck('nama')->toArray();
    }

    private function getLayananJaskug()
    {
        return LayananJasaKeuangan::select('nama')->get()->pluck('nama')->toArray();
    }

    public function getDetail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_ltk' => 'required|numeric|exists:verifikasi_ltk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                    'status' => 'ERROR',
                ], 400);
            }

            $bulanIndonesia = [
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember'
            ];

            $ltk = VerifikasiLtk::select(
                'verifikasi_ltk.id',
                'verifikasi_ltk.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'verifikasi_ltk.bulan',
                'verifikasi_ltk.tahun',
                'verifikasi_ltk.mtd_akuntansi',
                'verifikasi_ltk.verifikasi_akuntansi',
                'verifikasi_ltk.biaya_pso',
                'verifikasi_ltk.verifikasi_pso',
                'verifikasi_ltk.mtd_biaya_pos',
                'verifikasi_ltk.mtd_biaya_hasil',
                'verifikasi_ltk.proporsi_rumus',
                'verifikasi_ltk.verifikasi_proporsi',
                'verifikasi_ltk.keterangan',
                'verifikasi_ltk.catatan_pemeriksa',
                'verifikasi_ltk.nama_file',
                'verifikasi_ltk.kategori_cost',
            )->join('rekening_biaya', 'verifikasi_ltk.kode_rekening', '=', 'rekening_biaya.id')
                ->where('verifikasi_ltk.id', $request->id_ltk)
                ->where('verifikasi_ltk.keterangan', $request->proporsi_rumus ?? '!=', '0%')
                ->first();

            if (!$ltk) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Data LTK tidak ditemukan'
                ], 404);
            }

            $verifikasiPso = VerifikasiBiayaRutinDetail::where('id_rekening_biaya', '5000000010')
                ->sum('verifikasi');

            $kategoriCost = $ltk->keterangan;
            $mtdBiayaLtk = $ltk->mtd_akuntansi;
            $biayaPso = $ltk->biaya_pso;

            $proporsiCalculation = $this->calculateProporsiByCategory(
                $mtdBiayaLtk,
                $kategoriCost,
                $biayaPso,
                $ltk->tahun,
                $ltk->bulan
            );

            $isLock = LockVerifikasi::where('tahun', $ltk->tahun)->where('bulan', $ltk->bulan)->first();
            $isLockStatus = $isLock->status ?? false;

            $lastTwoDigits = substr($ltk->kode_rekening, -2);
            $mtd_biaya_hasil = $ltk->verifikasi_akuntansi - $verifikasiPso;

            $ltk->last_two_digits = $lastTwoDigits;
            $ltk->periode = $bulanIndonesia[$ltk->bulan - 1];
            $ltk->url_file = 'https://lpu.komdigi.go.id/backend/view_image/lampiranltk/' . $ltk->nama_file;

            $ltk->verifikasi_pso = "Rp " . number_format(round($verifikasiPso ?? 0), 0, ',', '.');
            $ltk->mtd_akuntansi = "Rp " . number_format(round($ltk->mtd_akuntansi ?? 0), 0, ',', '.');
            $ltk->verifikasi_akuntansi = "Rp " . number_format(round($ltk->verifikasi_akuntansi ?? 0), 0, ',', '.');
            $ltk->biaya_pso = "Rp " . number_format(round($ltk->biaya_pso ?? 0), 0, ',', '.');
            $ltk->mtd_biaya_pos = "Rp " . number_format(round($ltk->mtd_biaya_pos ?? 0), 0, ',', '.');
            $ltk->mtd_biaya_hasil = "Rp " . number_format(round($mtd_biaya_hasil ?? 0), 0, ',', '.');
            $ltk->verifikasi_proporsi = number_format($ltk->verifikasi_proporsi ?? 0, 2, ',', '.') . '%';
            $ltk->proporsi_rumus = $ltk->keterangan ?? $ltk->proporsi_rumus;

            // Merge calculation results
            foreach ($proporsiCalculation as $key => $value) {
                $ltk->$key = $value;
            }

            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'data' => [$ltk],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function verifikasi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_ltk' => 'required|numeric|exists:verifikasi_ltk,id',
                'data.*.verifikasi_akuntansi' => 'required|string',
                'data.*.verifikasi_pso' => 'required|string',
                'data.*.verifikasi_proporsi' => 'required|string',
                'data.*.catatan_pemeriksa' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $verifikasiData = $request->input('data');

            if (is_null($verifikasiData) || !is_array($verifikasiData)) {
                return response()->json(['status' => 'ERROR', 'message' => 'Struktur data tidak valid'], 400);
            }

            $updatedData = [];
            foreach ($verifikasiData as $data) {
                if (!isset($data['id_ltk'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Struktur data tidak valid, id_ltk tidak ditemukan'], 400);
                }

                $ltk = VerifikasiLtk::find($data['id_ltk']);

                if (!$ltk) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya rutin tidak ditemukan'], 404);
                }

                $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
                $verifikasiAkuntansi = (float) str_replace(['Rp', '.', ','], '', $data['verifikasi_akuntansi']);
                $verifikasiPso = (float) str_replace(['Rp', '.', ','], '', $data['verifikasi_pso']);
                $verifikasiProporsi = (float) str_replace(['%', ','], ['', '.'], $data['verifikasi_proporsi']);
                $tahun = $ltk->tahun;

                $ltk->update([
                    'verifikasi_akuntansi' => $verifikasiAkuntansi,
                    'verifikasi_pso' => $verifikasiPso,
                    'verifikasi_proporsi' => $verifikasiProporsi,
                    'id_status' => 9,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                ]);

                // Bagian update VerifikasiBiayaRutinDetail dihapus untuk mematikan link

                $updatedData[] = $ltk->fresh();
            }

            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Verifikasi Data LTK',
                'modul' => 'LTK',
                'id_user' => Auth::user()->id,
            ]);

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
