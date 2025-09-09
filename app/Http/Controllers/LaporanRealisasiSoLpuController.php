<?php

namespace App\Http\Controllers;

use App\Exports\LaporanRealisasiSoLpuExport;
use App\Models\Kpc;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanRealisasiSoLpuController extends Controller
{

    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 100);
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $id_regional = $request->get('id_regional', '');
            $id_kprk = $request->get('id_kprk', '');
            $tahun = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');

            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'biaya_atribusi.triwulan ASC',
                'triwulanDESC' => 'biaya_atribusi.triwulan DESC',
                'tahunASC' => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC' => 'biaya_atribusi.tahun_anggaran DESC',
            ];
            $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => 'in:' . implode(',', array_keys($orderMappings)),
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

            // subquery-subquery
            $subquery_vlu = DB::table('produksi_detail')
                ->select(
                    'produksi.id_kpc as id_kpc',
                    'produksi.tahun_anggaran',
                    'produksi.triwulan',
                    DB::raw('SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpu'),
                    DB::raw('SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpu'),
                    DB::raw('SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpu')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN POS UNIVERSAL')
                ->groupBy('id_kpc');

            $subquery_vlk = DB::table('produksi_detail')
                ->select(
                    'produksi.id_kpc as id_kpc',
                    'produksi.tahun_anggaran',
                    'produksi.triwulan',
                    DB::raw('SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpk'),
                    DB::raw('SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpk'),
                    DB::raw('SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpk')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN POS KOMERSIL')
                ->groupBy('id_kpc');

            $subquery_vlb = DB::table('produksi_detail')
                ->select(
                    'produksi.id_kpc as id_kpc',
                    'produksi.tahun_anggaran',
                    'produksi.triwulan',
                    DB::raw('SUM(verifikasi*rtarif*(tpkirim/100)) AS outgoing_lbf')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE')
                ->groupBy('id_kpc');

            $subquery_vbr = DB::table('verifikasi_biaya_rutin_detail')
                ->select(
                    'verifikasi_biaya_rutin.id_kpc as id_kpc',
                    'verifikasi_biaya_rutin.tahun',
                    'verifikasi_biaya_rutin.triwulan',
                    DB::raw('SUM(IF(id_verifikasi_biaya_rutin,verifikasi,0)) AS verifikasi_rutin')
                )
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->groupBy('id_kpc');

            $subquery_vba = DB::table('biaya_atribusi_detail')
                ->select(
                    'biaya_atribusi.id_kpc as id_kpc',
                    'biaya_atribusi.tahun',
                    'biaya_atribusi.triwulan',
                    DB::raw('SUM(IF(id_biaya_atribusi,verifikasi,0)) AS verifikasi_atribusi')
                )
                ->leftJoin('biaya_atribusi', 'biaya_atribusi.id', '=', 'biaya_atribusi_detail.id_biaya_atribusi')
                ->groupBy('id_kpc');

            // query utama
            $query = Kpc::select([
                'kpc.nama AS nama',
                'kpc.nomor_dirian AS nomor_dirian',
                'produksi.tahun_anggaran AS tahun',
                'produksi.triwulan AS triwulan',
                'produksi.id AS id_verifikasi_produksi',
                'verifikasi_lpu.outgoing_lpu',
                'verifikasi_lpu.incoming_lpu',
                'verifikasi_lpu.sisa_layanan_lpu',
                'verifikasi_lpk.outgoing_lpk',
                'verifikasi_lpk.incoming_lpk',
                'verifikasi_lpk.sisa_layanan_lpk',
                'verifikasi_lbf.outgoing_lbf',
                'verifikasi_biaya.verifikasi_rutin',
                'atribusi.verifikasi_atribusi',
            ])
                ->join('produksi', 'produksi.id_kpc', '=', 'kpc.id')
                ->when($id_regional !== '', fn($q) => $q->where('kpc.id_regional', $id_regional))
                ->when($id_kprk !== '', fn($q) => $q->where('kpc.id', $id_kprk))
                ->leftJoinSub($subquery_vlu, 'verifikasi_lpu', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lpu.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lpu.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lpu.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', fn($q) => $q->where('produksi.triwulan', $triwulan))
                        ->when($tahun !== '', fn($q) => $q->where('produksi.tahun_anggaran', $tahun));
                })
                ->leftJoinSub($subquery_vlk, 'verifikasi_lpk', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lpk.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lpk.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lpk.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', fn($q) => $q->where('produksi.triwulan', $triwulan))
                        ->when($tahun !== '', fn($q) => $q->where('produksi.tahun_anggaran', $tahun));
                })
                ->leftJoinSub($subquery_vlb, 'verifikasi_lbf', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lbf.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lbf.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lbf.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', fn($q) => $q->where('produksi.triwulan', $triwulan))
                        ->when($tahun !== '', fn($q) => $q->where('produksi.tahun_anggaran', $tahun));
                })
                ->leftJoinSub($subquery_vbr, 'verifikasi_biaya', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_biaya.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_biaya.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_biaya.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', fn($q) => $q->where('produksi.triwulan', $triwulan))
                        ->when($tahun !== '', fn($q) => $q->where('produksi.tahun_anggaran', $tahun));
                })
                ->leftJoinSub($subquery_vba, 'atribusi', function ($join) use ($triwulan, $tahun) {
                    $join->on('atribusi.id_kpc', '=', 'kpc.id')
                        ->on('atribusi.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('atribusi.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', fn($q) => $q->where('produksi.triwulan', $triwulan))
                        ->when($tahun !== '', fn($q) => $q->where('produksi.tahun_anggaran', $tahun));
                });

            // cache key
            $cacheKey = 'rekap_kpc_' . md5(json_encode([
                'tahun' => $tahun,
                'triwulan' => $triwulan,
                'id_regional' => $id_regional,
                'id_kprk' => $id_kprk,
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ]));

            $cachedData = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($query, $order, $offset, $limit) {
                $result = (clone $query)
                    ->groupBy('kpc.id')
                    ->orderByRaw($order)
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

                $total_data = (clone $query)
                    ->groupBy('kpc.id')
                    ->get()
                    ->count();

                return compact('result', 'total_data');
            });

            $result = $cachedData['result'];
            $total_data = $cachedData['total_data'];

            $data = [];
            foreach ($result as $item) {
                $rutin = $item->verifikasi_rutin ?? 0;
                $atribusi = $item->verifikasi_atribusi ?? 0;
                $biaya = $rutin + $atribusi;

                $verifikasi_incoming = ($item->incoming_lpu ?? 0) + ($item->incoming_lpk ?? 0);
                $verifikasi_outgoing = ($item->outgoing_lpu ?? 0) + ($item->outgoing_lpk ?? 0) + ($item->outgoing_lbf ?? 0);
                $verifikasi_sisa_layanan = ($item->sisa_layanan_lpu ?? 0) + ($item->sisa_layanan_lpk ?? 0);
                $jumlah = $verifikasi_incoming + $verifikasi_outgoing + $verifikasi_sisa_layanan;
                $selisih = $jumlah - $biaya;

                $data[] = [
                    'nomor_dirian' => $item->nomor_dirian,
                    'nama_kpc' => $item->nama,
                    'verifikasi_incoming' => $verifikasi_incoming,
                    'verifikasi_outgoing' => $verifikasi_outgoing,
                    'verifikasi_sisa_layanan' => $verifikasi_sisa_layanan,
                    'jumlah' => $jumlah,
                    'biaya' => $biaya,
                    'selisih' => $selisih,
                ];
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                // 'id_kprk' => 'nullable|numeric|exists:kprk,id',
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
            $id_regional = request()->get('id_regional', '');
            $id_kprk = request()->get('id_kprk', '');
            $tahun = request()->get('tahun', '');

            $triwulan = request()->get('triwulan', '');
            $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'biaya_atribusi.triwulan ASC',
                'triwulanDESC' => 'biaya_atribusi.triwulan DESC',
                'tahunASC' => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC' => 'biaya_atribusi.tahun_anggaran DESC',
            ];

            // Set the order based on the mapping or use the default order if not found
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            // Validation rules for input parameters
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
            // $query = Kpc::select([
            //     'kpc.nama AS nama',
            //     'kpc.nomor_dirian AS nomor_dirian',
            //     'produksi.tahun_anggaran AS tahun',
            //     'produksi.triwulan AS triwulan',
            //     'produksi.id AS id_verifikasi_produksi',
            //     'verifikasi_lpu.outgoing_lpu as verifikasi_outgoing_lpu',
            //     'verifikasi_lpu.incoming_lpu as verifikasi_incoming_lpu',
            //     'verifikasi_lpu.sisa_layanan_lpu as verifikasi_sisa_layanan_lpu',
            //     'verifikasi_lpk.outgoing_lpk as verifikasi_outgoing_lpk',
            //     'verifikasi_lpk.incoming_lpk as verifikasi_incoming_lpk',
            //     'verifikasi_lpk.sisa_layanan_lpk as verifikasi_sisa_layanan_lpk',
            //     'verifikasi_lbf.outgoing_lbf as verifikasi_outgoing_lbf',
            //     'verifikasi_biaya.verifikasi_rutin AS verifikasi_rutin',
            //     'atribusi.verifikasi_atribusi AS verifikasi_atribusi',
            // ])
            //     ->join('produksi', 'produksi.id_kpc', '=', 'kpc.id')
            //     ->leftJoin(DB::raw('(SELECT produksi.id_kpc as id_kpc, produksi.tahun_anggaran as tahun_anggaran, produksi.triwulan as triwulan,
            //         SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpu,
            //         SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpu,
            //         SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpu
            //         FROM produksi_detail
            //         LEFT JOIN produksi ON produksi.id=produksi_detail.id_produksi
            //         WHERE kategori_produksi="LAYANAN POS UNIVERSAL"
            //         GROUP BY id_kpc
            //     ) as verifikasi_lpu'), function ($join) {
            //         $join->on('verifikasi_lpu.id_kpc', '=', 'kpc.id')
            //             ->on('verifikasi_lpu.tahun_anggaran', '=', 'produksi.tahun_anggaran')
            //             ->on('verifikasi_lpu.triwulan', '=', 'produksi.triwulan');
            //     })
            //     ->leftJoin(DB::raw('(SELECT produksi.id_kpc as id_kpc, produksi.tahun_anggaran as tahun_anggaran, produksi.triwulan as triwulan,
            //         SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpk,
            //         SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpk,
            //         SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpk
            //         FROM produksi_detail
            //         LEFT JOIN produksi ON produksi.id=produksi_detail.id_produksi
            //         WHERE kategori_produksi="LAYANAN POS KOMERSIL"
            //         GROUP BY id_kpc
            //     ) as verifikasi_lpk'), function ($join) {
            //         $join->on('verifikasi_lpk.id_kpc', '=', 'kpc.id')
            //             ->on('verifikasi_lpk.tahun_anggaran', '=', 'produksi.tahun_anggaran')
            //             ->on('verifikasi_lpk.triwulan', '=', 'produksi.triwulan');
            //     })
            //     ->leftJoin(DB::raw('(SELECT produksi.id_kpc as id_kpc, produksi.tahun_anggaran as tahun_anggaran, produksi.triwulan as triwulan,
            //         SUM(verifikasi*rtarif*(tpkirim/100)) AS outgoing_lbf
            //         FROM produksi_detail
            //         LEFT JOIN produksi ON produksi.id=produksi_detail.id_produksi
            //         WHERE kategori_produksi="LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE"
            //         GROUP BY id_kpc
            //     ) as verifikasi_lbf'), function ($join) {
            //         $join->on('verifikasi_lbf.id_kpc', '=', 'kpc.id')
            //             ->on('verifikasi_lbf.tahun_anggaran', '=', 'produksi.tahun_anggaran')
            //             ->on('verifikasi_lbf.triwulan', '=', 'produksi.triwulan');
            //     })
            //     ->leftJoin(DB::raw('(SELECT verifikasi_biaya_rutin.id_kpc as id_kpc, verifikasi_biaya_rutin.tahun AS tahun, verifikasi_biaya_rutin.triwulan AS triwulan,
            //         SUM(IF(id_verifikasi_biaya_rutin,verifikasi,0)) AS verifikasi_rutin
            //         FROM verifikasi_biaya_rutin_detail
            //         LEFT JOIN verifikasi_biaya_rutin ON verifikasi_biaya_rutin.id = verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin
            //         GROUP BY id_kpc
            //     ) as verifikasi_biaya'), function ($join) {
            //         $join->on('verifikasi_biaya.id_kpc', '=', 'kpc.id')
            //             ->on('verifikasi_biaya.tahun', '=', 'produksi.tahun_anggaran')
            //             ->on('verifikasi_biaya.triwulan', '=', 'produksi.triwulan');
            //     })
            //     ->leftJoin(DB::raw('(SELECT biaya_atribusi.id_kpc as id_kpc, biaya_atribusi.tahun AS tahun, biaya_atribusi.triwulan AS triwulan,
            //         SUM(IF(id_biaya_atribusi,verifikasi,0)) AS verifikasi_atribusi
            //         FROM biaya_atribusi_detail
            //         LEFT JOIN biaya_atribusi ON biaya_atribusi.id = biaya_atribusi_detail.id_biaya_atribusi
            //         GROUP BY id_kpc
            //     ) as atribusi'), function ($join) {
            //         $join->on('atribusi.id_kpc', '=', 'kpc.id')
            //             ->on('atribusi.tahun', '=', 'produksi.tahun_anggaran')
            //             ->on('atribusi.triwulan', '=', 'produksi.triwulan');
            //     })
            //     ->groupBy('kpc.id');
            // $result = $query->get();
            $subquery_vlu = DB::table('produksi_detail')
                ->select(
                    'produksi.id_kpc as id_kpc',
                    'produksi.tahun_anggaran as tahun_anggaran',
                    'produksi.triwulan as triwulan',
                    DB::raw('SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpu'),
                    DB::raw('SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpu'),
                    DB::raw('SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpu')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN POS UNIVERSAL')
                ->groupBy('id_kpc');

            $subquery_vlk = DB::table('produksi_detail')
                ->select(
                    'produksi.id_kpc as id_kpc',
                    'produksi.tahun_anggaran as tahun_anggaran',
                    'produksi.triwulan as triwulan',
                    DB::raw('SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS outgoing_lpk'),
                    DB::raw('SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS incoming_lpk'),
                    DB::raw('SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS sisa_layanan_lpk')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN POS KOMERSIL')
                ->groupBy('id_kpc');

            $subquery_vlb = DB::table('produksi_detail')
                ->select(
                    'produksi.id_kpc as id_kpc',
                    'produksi.tahun_anggaran as tahun_anggaran',
                    'produksi.triwulan as triwulan',
                    DB::raw('SUM(verifikasi*rtarif*(tpkirim/100)) AS outgoing_lbf')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->where('kategori_produksi', 'LAYANAN TRANSAKSI KEUANGAN BERBASIS FEE')
                ->groupBy('id_kpc');

            $subquery_vbr = DB::table('verifikasi_biaya_rutin_detail')
                ->select(
                    'verifikasi_biaya_rutin.id_kpc as id_kpc',
                    'verifikasi_biaya_rutin.tahun AS tahun',
                    'verifikasi_biaya_rutin.triwulan AS triwulan',
                    DB::raw('SUM(IF(id_verifikasi_biaya_rutin,verifikasi,0)) AS verifikasi_rutin')
                )
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->groupBy('id_kpc');

            $subquery_vba = DB::table('biaya_atribusi_detail')
                ->select(
                    'biaya_atribusi.id_kpc as id_kpc',
                    'biaya_atribusi.tahun AS tahun',
                    'biaya_atribusi.triwulan AS triwulan',
                    DB::raw('SUM(IF(id_biaya_atribusi,verifikasi,0)) AS verifikasi_atribusi')
                )
                ->leftJoin('biaya_atribusi', 'biaya_atribusi.id', '=', 'biaya_atribusi_detail.id_biaya_atribusi')
                ->groupBy('id_kpc');

            $query = Kpc::select([
                'kpc.nama AS nama',
                'kpc.nomor_dirian AS nomor_dirian',
                'produksi.tahun_anggaran AS tahun',
                'produksi.triwulan AS triwulan',
                'produksi.id AS id_verifikasi_produksi',
                'verifikasi_lpu.outgoing_lpu as verifikasi_outgoing_lpu',
                'verifikasi_lpu.incoming_lpu as verifikasi_incoming_lpu',
                'verifikasi_lpu.sisa_layanan_lpu as verifikasi_sisa_layanan_lpu',
                'verifikasi_lpk.outgoing_lpk as verifikasi_outgoing_lpk',
                'verifikasi_lpk.incoming_lpk as verifikasi_incoming_lpk',
                'verifikasi_lpk.sisa_layanan_lpk as verifikasi_sisa_layanan_lpk',
                'verifikasi_lbf.outgoing_lbf as verifikasi_outgoing_lbf',
                'verifikasi_biaya.verifikasi_rutin AS verifikasi_rutin',
                'atribusi.verifikasi_atribusi AS verifikasi_atribusi',
            ])
                ->join('produksi', 'produksi.id_kpc', '=', 'kpc.id')
                ->when($id_regional !== '', function ($query) use ($id_regional) {
                    return $query->where('kpc.id_regional', $id_regional);
                })
                ->when($id_kprk !== '', function ($query) use ($id_kprk) {
                    return $query->where('kpc.id', $id_kprk);
                })
                ->leftJoinSub($subquery_vlu, 'verifikasi_lpu', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lpu.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lpu.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lpu.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($subquery_vlk, 'verifikasi_lpk', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lpk.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lpk.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lpk.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($subquery_vlb, 'verifikasi_lbf', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_lbf.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_lbf.tahun_anggaran', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_lbf.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($subquery_vbr, 'verifikasi_biaya', function ($join) use ($triwulan, $tahun) {
                    $join->on('verifikasi_biaya.id_kpc', '=', 'kpc.id')
                        ->on('verifikasi_biaya.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('verifikasi_biaya.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->leftJoinSub($subquery_vba, 'atribusi', function ($join) use ($triwulan, $tahun) {
                    $join->on('atribusi.id_kpc', '=', 'kpc.id')
                        ->on('atribusi.tahun', '=', 'produksi.tahun_anggaran')
                        ->on('atribusi.triwulan', '=', 'produksi.triwulan')
                        ->when($triwulan !== '', function ($query) use ($triwulan) {
                            $query->where('produksi.triwulan', $triwulan);
                        })
                        ->when($tahun !== '', function ($query) use ($tahun) {
                            $query->where('produksi.tahun_anggaran', $tahun);
                        });
                })
                ->groupBy('kpc.id')
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            $result = $query->get();

            $data = [];
            foreach ($result as $item) {
                $biaya = $item->rutin + $item->atribusi;
                $verifikasi_incoming = $item->verifikasi_incoming_lpu + $item->verifikasi_incoming_lpk;
                $verifikasi_outgoing = $item->verifikasi_outgoing_lpu + $item->verifikasi_outgoing_lpk + $item->verifikasi_outgoing_lbf;
                $verifikasi_sisa_layanan = $item->verifikasi_sisa_layanan_lpu + $item->verifikasi_sisa_layanan_lpk;
                $jumlah = $verifikasi_incoming + $verifikasi_outgoing + $verifikasi_sisa_layanan;
                $selisih = $jumlah - $biaya;
                $data[] = [
                    'nomor_dirian' => $item->nomor_dirian,
                    'nama_kpc' => $item->nama,
                    'verifikasi_incoming' => $verifikasi_incoming,
                    'verifikasi_outgoing' => $verifikasi_outgoing,
                    'verifikasi_sisa_layanan' => $verifikasi_sisa_layanan,
                    'jumlah' => $jumlah,
                    'biaya' => $biaya,
                    'selisih' => $selisih,
                ];
            }
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Cetak Laporan Realisasi SO LPU',
                'modul' => 'Laporan Realisasi SO LPU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            $export = new LaporanRealisasiSoLpuExport($data);
            $spreadsheet = $export->getSpreadsheet();
            $writer = new Xlsx($spreadsheet);

            $filename = 'laporan-realisasi-dana-lpu.xlsx';
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
