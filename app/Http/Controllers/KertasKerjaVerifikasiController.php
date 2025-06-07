<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKertasKerjaVerifikasiDetailExport;
use App\Exports\LaporanKertasKerjaVerifikasiExport;
use App\Models\Kpc;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;

class KertasKerjaVerifikasiController extends Controller
{
    public function index(Request $request)
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
            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
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
            // // Subquery untuk vbrd
            // $subquery_vbrd = DB::table('verifikasi_biaya_rutin_detail')
            //     ->select(
            //         'id_kpc',
            //         'id_kprk',
            //         // 'tahun',
            //         DB::raw('SUM(pelaporan) AS pelaporan'),
            //         DB::raw('SUM(verifikasi) AS hasil_verifikasi')
            //     )
            //     ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
            //     ->when($triwulan !== '', function ($query) use ($triwulan) {
            //         return $query->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            //     })
            //     ->when($tahun !== '', function ($query) use ($tahun) {
            //         return $query->where('verifikasi_biaya_rutin.tahun', $tahun);
            //     })
            //     ->when($id_regional !== '', function ($query) use ($id_regional) {
            //         return $query->where('verifikasi_biaya_rutin.id_regional', $id_regional);
            //     })
            //     ->groupBy('id_kprk');

            // $subquery_pd = DB::table('produksi_detail')
            //     ->select(
            //         'id_kpc',
            //         DB::raw('SUM(pelaporan) AS pelaporan'),
            //         DB::raw('SUM(verifikasi) AS hasil_verifikasi')
            //     )
            //     ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
            //     ->when($triwulan !== '', function ($query) use ($triwulan) {
            //         return $query->where('produksi.triwulan', $triwulan);
            //     })
            //     ->when($tahun !== '', function ($query) use ($tahun) {
            //         return $query->where('produksi.tahun_anggaran', $tahun);
            //     })
            //     ->when($id_regional !== '', function ($query) use ($id_regional) {
            //         return $query->where('produksi.id_regional', $id_regional);
            //     })
            //     ->groupBy('id_kprk');

            // $query = DB::table('kprk')
            //     ->select(
            //         'kprk.id AS id_kprk',
            //         'kprk.nama AS nama_kprk',
            //         // DB::raw('vbrd.tahun as tahun_pelaporan'),
            //         DB::raw('SUM(IFNULL(vbrd.pelaporan, 0)) AS hasil_pelaporan_biaya'),
            //         DB::raw('SUM(IFNULL(vbrd.hasil_verifikasi, 0)) AS hasil_verifikasi_biaya'),
            //         DB::raw('SUM(IFNULL(pd.pelaporan, 0)) AS hasil_pelaporan_pendapatan'),
            //         DB::raw('SUM(IFNULL(pd.hasil_verifikasi, 0)) AS hasil_verifikasi_pendapatan')
            //     )->when($id_regional !== '', function ($query) use ($id_regional) {
            //     return $query->where('kprk.id_regional', $id_regional);
            // })->when($id_kprk !== '', function ($query) use ($id_kprk) {
            //     return $query->where('kprk.id', $id_kprk);
            // })
            //     ->leftJoin('kpc', 'kprk.id', '=', 'kpc.id_kprk')
            //     ->leftJoinSub($subquery_vbrd, 'vbrd', 'kpc.id', '=', 'vbrd.id_kpc')
            //     ->leftJoinSub($subquery_pd, 'pd', 'kpc.id', '=', 'pd.id_kpc')
            //     ->groupBy('kprk.id');

            // $data = $query->get();
            // $result = [];
            // foreach ($data as $item) {
            //     $rekapitulasi = [
            //         'laporan' => [
            //             [
            //                 'hasil_pelaporan_biaya' => floatval($item->hasil_pelaporan_biaya),
            //             ],
            //             [
            //                 'hasil_pelaporan_pendapatan' => floatval($item->hasil_pelaporan_pendapatan),
            //             ],
            //         ],
            //         'verifikasi' => [
            //             [
            //                 'hasil_verifikasi_biaya' => floatval($item->hasil_verifikasi_biaya),
            //             ],
            //             [
            //                 'hasil_verifikasi_pendapatan' => floatval($item->hasil_verifikasi_pendapatan),
            //             ],
            //         ],
            //     ];

            //     $deviasi = [
            //         'laporan' => floatval($item->hasil_pelaporan_biaya) - floatval($item->hasil_pelaporan_pendapatan),
            //         'verifikasi' => floatval($item->hasil_verifikasi_biaya) - floatval($item->hasil_verifikasi_pendapatan),
            //     ];
            //     if ($deviasi) {}
            //     $result[] = [
            //         'id_kprk' => $item->id_kprk,
            //         'nama_kprk' => $item->nama_kprk,
            //         'REKAPITULASI' => $rekapitulasi,
            //         'deviasi' => $deviasi,
            //         'deviasi_verifikasi' => ($item->hasil_verifikasi_biaya - $item->hasil_verifikasi_pendapatan),
            //     ];
            // }
            // Subquery untuk vbrd
            $subquery_vbrd = DB::table('verifikasi_biaya_rutin_detail')
                ->select(
                    'id_kpc',
                    'id_kprk',
                    // 'tahun',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS hasil_verifikasi')
                )
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->when($triwulan !== '', function ($query) use ($triwulan) {
                    return $query->where('verifikasi_biaya_rutin.triwulan', $triwulan);
                })
                ->when($tahun !== '', function ($query) use ($tahun) {
                    return $query->where('verifikasi_biaya_rutin.tahun', $tahun);
                })
                ->when($id_regional !== '', function ($query) use ($id_regional) {
                    return $query->where('verifikasi_biaya_rutin.id_regional', $id_regional);
                })
                ->groupBy('id_kprk');

            $subquery_pd = DB::table('produksi_detail')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS hasil_verifikasi')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->when($triwulan !== '', function ($query) use ($triwulan) {
                    return $query->where('produksi.triwulan', $triwulan);
                })
                ->when($tahun !== '', function ($query) use ($tahun) {
                    return $query->where('produksi.tahun_anggaran', $tahun);
                })
                ->when($id_regional !== '', function ($query) use ($id_regional) {
                    return $query->where('produksi.id_regional', $id_regional);
                })
                ->groupBy('id_kprk');

            $query = DB::table('kprk')
                ->select(
                    'kprk.id AS id_kprk',
                    'kprk.nama AS nama_kprk',
                    // DB::raw('vbrd.tahun as tahun_pelaporan'),
                    DB::raw('SUM(IFNULL(vbrd.pelaporan, 0)) AS hasil_pelaporan_biaya'),
                    DB::raw('SUM(IFNULL(vbrd.hasil_verifikasi, 0)) AS hasil_verifikasi_biaya'),
                    DB::raw('SUM(IFNULL(pd.pelaporan, 0)) AS hasil_pelaporan_pendapatan'),
                    DB::raw('SUM(IFNULL(pd.hasil_verifikasi, 0)) AS hasil_verifikasi_pendapatan')
                )->when($id_regional !== '', function ($query) use ($id_regional) {
                return $query->where('kprk.id_regional', $id_regional);
            })->when($id_kprk !== '', function ($query) use ($id_kprk) {
                return $query->where('kprk.id', $id_kprk);
            })
                ->leftJoin('kpc', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoinSub($subquery_vbrd, 'vbrd', 'kpc.id', '=', 'vbrd.id_kpc')
                ->leftJoinSub($subquery_pd, 'pd', 'kpc.id', '=', 'pd.id_kpc')

                ->groupBy('kprk.id');
            $total_data = $query->count();
            $result = $query  ->orderByRaw($order)
            ->offset($offset)
            ->limit($limit)->get();
            $data = [];
            foreach ($result as $item) {
                $data[] = [
                    'id_kprk' => $item->id_kprk,
                    'nama_kprk' => $item->nama_kprk,
                    'hasil_pelaporan_biaya' => round($item->hasil_pelaporan_biaya ?? 0),
                    'hasil_pelaporan_pendapatan' => round($item->hasil_pelaporan_pendapatan ?? 0),
                    'hasil_verifikasi_biaya' => round($item->hasil_verifikasi_biaya ?? 0),
                    'hasil_verifikasi_pendapatan' => round($item->hasil_verifikasi_pendapatan ?? 0),
                    'deviasi_biaya' => round($item->hasil_pelaporan_biaya - $item->hasil_verifikasi_biaya ?? 0),
                    'deviasi_produksi' => round($item->hasil_pelaporan_pendapatan - $item->hasil_verifikasi_pendapatan ?? 0),
                    'deviasi_akhir' => round($item->hasil_verifikasi_biaya - $item->hasil_verifikasi_pendapatan ?? 0),
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
            // dd($e);
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function show(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'id_kprk' => 'nullable|numeric|exists:kprk,id',
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
            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
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
            // Subquery untuk vbrd
            $subquery_vbrd = DB::table('verifikasi_biaya_rutin_detail')
                ->select(
                    'id_kpc',
                    'id_kprk',
                    'tahun',
                    'triwulan',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS hasil_verifikasi')
                )
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->when($triwulan !== '', function ($query) use ($triwulan) {
                    return $query->where('verifikasi_biaya_rutin.triwulan', $triwulan);
                })
                ->when($tahun !== '', function ($query) use ($tahun) {
                    // dd($tahun);
                    return $query->where('verifikasi_biaya_rutin.tahun', $tahun);
                })
                ->groupBy('id_kpc');

            $subquery_pd = DB::table('produksi_detail')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS hasil_verifikasi')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->when($triwulan !== '', function ($query) use ($triwulan) {
                    return $query->where('produksi.triwulan', $triwulan);
                })
                ->when($tahun !== '', function ($query) use ($tahun) {
                    return $query->where('produksi.tahun_anggaran', $tahun);
                })
                ->groupBy('id_kpc');

            // $query = DB::table('kpc')
            //     ->select(
            //         'kpc.id AS id_kpc',
            //         'kpc.nama AS nama_kpc',
            //         DB::raw('vbrd.triwulan'),
            //         DB::raw('vbrd.tahun'),
            //         DB::raw('SUM(IFNULL(vbrd.pelaporan, 0)) AS hasil_pelaporan_biaya'),
            //         DB::raw('SUM(IFNULL(vbrd.hasil_verifikasi, 0)) AS hasil_verifikasi_biaya'),
            //         DB::raw('SUM(IFNULL(pd.pelaporan, 0)) AS hasil_pelaporan_pendapatan'),
            //         DB::raw('SUM(IFNULL(pd.hasil_verifikasi, 0)) AS hasil_verifikasi_pendapatan')
            //     )->when($id_regional !== '', function ($query) use ($id_regional) {
            //     return $query->where('kpc.id_regional', $id_regional);
            // })->when($id_kprk !== '', function ($query) use ($id_kprk) {
            //     return $query->where('kpc.id_kprk', $id_kprk);
            // })->leftJoinSub($subquery_vbrd, 'vbrd', 'kpc.id', '=', 'vbrd.id_kpc')
            //     ->leftJoinSub($subquery_pd, 'pd', 'kpc.id', '=', 'pd.id_kpc')
            //     ->groupBy('kpc.id');

            // $data = $query->get();
            // $result = [];
            // foreach ($data as $item) {
            //     $rekapitulasi = [
            //         'laporan' => [
            //             [
            //                 'hasil_pelaporan_biaya' => $item->hasil_pelaporan_biaya,
            //             ],
            //             [
            //                 'hasil_pelaporan_pendapatan' => $item->hasil_pelaporan_pendapatan,
            //             ],
            //         ],
            //         'verifikasi' => [
            //             [
            //                 'hasil_verifikasi_biaya' => $item->hasil_verifikasi_biaya,
            //             ],
            //             [
            //                 'hasil_verifikasi_pendapatan' => $item->hasil_verifikasi_pendapatan,
            //             ],
            //         ],
            //     ];

            //     $deviasi = [
            //         'biaya' => $item->hasil_pelaporan_biaya - $item->hasil_verifikasi_biaya,
            //         'pendapatan' => $item->hasil_pelaporan_pendapatan - $item->hasil_verifikasi_pendapatan,
            //     ];

            //     $result[] = [
            //         'id_kpc' => $item->id_kpc,
            //         'nama_kpc' => $item->nama_kpc,
            //         'tahun' => $item->tahun,
            //         'triwulan' => $item->triwulan,
            //         'REKAPITULASI' => $rekapitulasi,
            //         'deviasi' => $deviasi,
            //         'deviasi_verifikasi' => ($item->hasil_verifikasi_biaya - $item->hasil_verifikasi_pendapatan),
            //     ];
            // }

            $query = DB::table('kprk')
                ->select(
                    'kpc.nomor_dirian AS id_kpc',
                    'kpc.nama AS nama_kpc',
                    // DB::raw('vbrd.tahun as tahun_pelaporan'),
                    DB::raw('SUM(IFNULL(vbrd.pelaporan, 0)) AS hasil_pelaporan_biaya'),
                    DB::raw('SUM(IFNULL(vbrd.hasil_verifikasi, 0)) AS hasil_verifikasi_biaya'),
                    DB::raw('SUM(IFNULL(pd.pelaporan, 0)) AS hasil_pelaporan_pendapatan'),
                    DB::raw('SUM(IFNULL(pd.hasil_verifikasi, 0)) AS hasil_verifikasi_pendapatan')
                )->when($id_regional !== '', function ($query) use ($id_regional) {
                return $query->where('kprk.id_regional', $id_regional);
            })->when($id_kprk !== '', function ($query) use ($id_kprk) {
                return $query->where('kprk.id', $id_kprk);
            })
                ->leftJoin('kpc', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoinSub($subquery_vbrd, 'vbrd', 'kpc.id', '=', 'vbrd.id_kpc')
                ->leftJoinSub($subquery_pd, 'pd', 'kpc.id', '=', 'pd.id_kpc')
                ->groupBy('kpc.id');
               $total_data = $query->count();
            $result = $query->get();
            $data = [];
            foreach ($result as $item) {
                $data[] = [
                    'id_kpc' => $item->id_kpc,
                    'nama_kpc' => $item->nama_kpc,
                    'hasil_pelaporan_biaya' => round($item->hasil_pelaporan_biaya ?? 0),
                    'hasil_pelaporan_pendapatan' => round($item->hasil_pelaporan_pendapatan ?? 0),
                    'hasil_verifikasi_biaya' => round($item->hasil_verifikasi_biaya ?? 0),
                    'hasil_verifikasi_pendapatan' => round($item->hasil_verifikasi_pendapatan ?? 0),
                    'deviasi_biaya' => round($item->hasil_pelaporan_biaya - $item->hasil_verifikasi_biaya?? 0),
                    'deviasi_produksi' => round($item->hasil_pelaporan_pendapatan - $item->hasil_verifikasi_pendapatan?? 0),
                    'deviasi_akhir' => round($item->hasil_verifikasi_biaya - $item->hasil_verifikasi_pendapatan?? 0),
                ];
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $data,
            ]);
        } catch (\Exception $e) {

            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
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
            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
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
            // Subquery untuk vbrd
            $subquery_vbrd = DB::table('verifikasi_biaya_rutin_detail')
                ->select(
                    'id_kpc',
                    'id_kprk',
                    // 'tahun',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS hasil_verifikasi')
                )
                ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->when($triwulan !== '', function ($query) use ($triwulan) {
                    return $query->where('verifikasi_biaya_rutin.triwulan', $triwulan);
                })
                ->when($tahun !== '', function ($query) use ($tahun) {
                    return $query->where('verifikasi_biaya_rutin.tahun', $tahun);
                })
                ->when($id_regional !== '', function ($query) use ($id_regional) {
                    return $query->where('verifikasi_biaya_rutin.id_regional', $id_regional);
                })
                ->groupBy('id_kprk');

            $subquery_pd = DB::table('produksi_detail')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) AS pelaporan'),
                    DB::raw('SUM(verifikasi) AS hasil_verifikasi')
                )
                ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->when($triwulan !== '', function ($query) use ($triwulan) {
                    return $query->where('produksi.triwulan', $triwulan);
                })
                ->when($tahun !== '', function ($query) use ($tahun) {
                    return $query->where('produksi.tahun_anggaran', $tahun);
                })
                ->when($id_regional !== '', function ($query) use ($id_regional) {
                    return $query->where('produksi.id_regional', $id_regional);
                })
                ->groupBy('id_kprk');

            $query = DB::table('kprk')
                ->select(
                    'kprk.id AS id_kprk',
                    'kprk.nama AS nama_kprk',
                    // DB::raw('vbrd.tahun as tahun_pelaporan'),
                    DB::raw('SUM(IFNULL(vbrd.pelaporan, 0)) AS hasil_pelaporan_biaya'),
                    DB::raw('SUM(IFNULL(vbrd.hasil_verifikasi, 0)) AS hasil_verifikasi_biaya'),
                    DB::raw('SUM(IFNULL(pd.pelaporan, 0)) AS hasil_pelaporan_pendapatan'),
                    DB::raw('SUM(IFNULL(pd.hasil_verifikasi, 0)) AS hasil_verifikasi_pendapatan')
                )->when($id_regional !== '', function ($query) use ($id_regional) {
                return $query->where('kprk.id_regional', $id_regional);
            })->when($id_kprk !== '', function ($query) use ($id_kprk) {
                return $query->where('kprk.id', $id_kprk);
            })
                ->leftJoin('kpc', 'kprk.id', '=', 'kpc.id_kprk')
                ->leftJoinSub($subquery_vbrd, 'vbrd', 'kpc.id', '=', 'vbrd.id_kpc')
                ->leftJoinSub($subquery_pd, 'pd', 'kpc.id', '=', 'pd.id_kpc')
                ->groupBy('kprk.id');

            $result = $query->get();
            $data = [];
            foreach ($result as $item) {
                    $data[] = [
                        'id_kprk' => $item->id_kprk,
                        'nama_kprk' => $item->nama_kprk,
                        'hasil_pelaporan_biaya' => round($item->hasil_pelaporan_biaya ?? 0),
                        'hasil_pelaporan_pendapatan' => round($item->hasil_pelaporan_pendapatan ?? 0),
                        'hasil_verifikasi_biaya' => round($item->hasil_verifikasi_biaya ?? 0),
                        'hasil_verifikasi_pendapatan' => round($item->hasil_verifikasi_pendapatan ?? 0),
                        'deviasi_biaya' => round($item->hasil_pelaporan_biaya - $item->hasil_verifikasi_biaya ?? 0),
                        'deviasi_produksi' => round($item->hasil_pelaporan_pendapatan - $item->hasil_verifikasi_pendapatan ?? 0),
                        'deviasi_akhir' => round($item->hasil_verifikasi_biaya - $item->hasil_verifikasi_pendapatan ?? 0),
                    ];
            }
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Cetak Kertas Keja Verifikasi',
                'modul' => 'Kertas Keja Verifikasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return Excel::download(new LaporanKertasKerjaVerifikasiExport($data), 'template-laporan_kertas_kerja_verifikasi.xlsx');
        } catch (\Exception $e) {

            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }

    }
    public function collection()
    {
        // Kembalikan data yang ingin diekspor
        return $data;
    }

    public function exportDetail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'id_kprk' => 'nullable|numeric|exists:kprk,id',
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
            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
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

            // $subqueryAlokasiDana = DB::table('alokasi_dana')
            //     ->selectRaw('alokasi_dana.id_kpc AS id_kpc')
            //     ->selectRaw('alokasi_dana.tahun AS tahun')
            //     ->selectRaw('alokasi_dana.triwulan AS triwulan')
            //     ->selectRaw('SUM(alokasi_dana_lpu) AS alokasi_dana_lpu')
            //     ->groupBy('id_kpc');

            // $subqueryAtribusi = DB::table('verifikasi_biaya_atribusi_detail')
            //     ->selectRaw('verifikasi_biaya_atribusi.id_kpc AS id_kpc')
            //     ->selectRaw('verifikasi_biaya_atribusi.tahun AS tahun')
            //     ->selectRaw('verifikasi_biaya_atribusi.triwulan AS triwulan')
            //     ->selectRaw('SUM(pelaporan) AS pelaporan')
            //     ->selectRaw('SUM(verifikasi) AS verifikasi')
            //     ->leftJoin('verifikasi_biaya_atribusi', 'verifikasi_biaya_atribusi.id', '=', 'verifikasi_biaya_atribusi_detail.id_verifikasi_biaya_atribusi')
            //     ->groupBy('id_kpc');

            // $subqueryBiaya = DB::table('verifikasi_biaya_rutin_detail')
            //     ->selectRaw('verifikasi_biaya_rutin.id_kpc AS id_kpc')
            //     ->selectRaw('verifikasi_biaya_rutin.tahun AS tahun')
            //     ->selectRaw('verifikasi_biaya_rutin.triwulan AS triwulan')
            //     ->selectRaw('SUM(pelaporan) AS pelaporan')
            //     ->selectRaw('SUM(verifikasi) AS verifikasi')
            //     ->leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
            //     ->groupBy('id_kpc');

            // $subqueryVerifikasiLpu = DB::table('produksi_detail')
            //     ->selectRaw('produksi.id_kpc AS id_kpc')
            //     ->selectRaw('produksi.tahun_anggaran AS tahun_anggaran')
            //     ->selectRaw('produksi.triwulan AS triwulan')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="PENERIMAAN/OUTGOING" THEN (pelaporan * rtarif * (tpkirim / 100)) ELSE 0 END) AS pelaporan_outgoing_lpu')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="PENGELUARAN/INCOMING" THEN (pelaporan * rtarif * (tpkirim / 100)) ELSE 0 END) AS pelaporan_incoming_lpu')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="SISA LAYANAN" THEN (pelaporan * rtarif * (tpkirim / 100)) ELSE 0 END) AS pelaporan_sisa_layanan_lpu')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="PENERIMAAN/OUTGOING" THEN (verifikasi * rtarif * (tpkirim / 100)) ELSE 0 END) AS verifikasi_outgoing_lpu')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="PENGELUARAN/INCOMING" THEN (verifikasi * rtarif * (tpkirim / 100)) ELSE 0 END) AS verifikasi_incoming_lpu')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="SISA LAYANAN" THEN (verifikasi * rtarif * (tpkirim / 100)) ELSE 0 END) AS verifikasi_sisa_layanan_lpu')
            //     ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
            //     ->where(function ($query) {
            //         $query->where('kategori_produksi', 'LAYANAN POS UNIVERSAL')
            //             ->orWhere('kategori_produksi', 'LAYANAN POS KOMERSIL');
            //     })
            //     ->groupBy('id_kpc');
            // $subqueryVerifikasi = DB::table('produksi_detail')
            //     ->selectRaw('produksi.id_kpc AS id_kpc')
            //     ->selectRaw('produksi.tahun_anggaran AS tahun_anggaran')
            //     ->selectRaw('produksi.triwulan AS triwulan')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="PENERIMAAN/OUTGOING" THEN (verifikasi*rtarif*(tpkirim/100)) ELSE 0 END) AS verifikasi_outgoing')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="SISA LAYANAN" THEN (verifikasi*rtarif*(tpkirim/100)) ELSE 0 END) AS verifikasi_sisa_layanan')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="SISA LAYANAN" THEN (pelaporan*rtarif*(tpkirim/100)) ELSE 0 END) AS pelaporan_sisa_layanan')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="PENERIMAAN/OUTGOING" THEN (pelaporan*rtarif*(tpkirim/100)) ELSE 0 END) AS pelaporan_outgoing')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="PENGELUARAN/INCOMING" THEN (pelaporan*rtarif*(tpkirim/100)) ELSE 0 END) AS pelaporan_incoming')
            //     ->selectRaw('SUM(CASE WHEN jenis_produksi="PENGELUARAN/INCOMING" THEN (verifikasi*rtarif*(tpkirim/100)) ELSE 0 END) AS verifikasi_incoming')
            //     ->leftJoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
            //     ->groupBy('id_kpc');
            // $query = Kpc::select([
            //     'kpc.id AS id_kpc',
            //     'kpc.nama AS nama_kpc',
            //     'verifikasi.pelaporan_outgoing AS pelaporan_outgoing',
            //     'verifikasi.pelaporan_incoming AS pelaporan_incoming',
            //     'verifikasi.verifikasi_incoming AS verifikasi_incoming',
            //     'verifikasi.pelaporan_sisa_layanan AS pelaporan_sisa_layanan',
            //     'verifikasi.verifikasi_outgoing AS verifikasi_outgoing',
            //     'verifikasi.verifikasi_sisa_layanan AS verifikasi_sisa_layanan',
            //     'verifikasi_lpu.pelaporan_outgoing_lpu AS pelaporan_outgoing_lpu',
            //     'verifikasi_lpu.pelaporan_incoming_lpu AS pelaporan_incoming_lpu',
            //     'verifikasi_lpu.pelaporan_sisa_layanan_lpu AS pelaporan_sisa_layanan_lpu',
            //     'verifikasi_lpu.verifikasi_outgoing_lpu AS verifikasi_outgoing_lpu',
            //     'verifikasi_lpu.verifikasi_incoming_lpu AS verifikasi_incoming_lpu',
            //     'verifikasi_lpu.verifikasi_sisa_layanan_lpu AS verifikasi_sisa_layanan_lpu',
            //     'atribusi.pelaporan AS pelaporan_atribusi',
            //     'atribusi.verifikasi AS verifikasi_atribusi',
            //     'biaya.pelaporan AS pelaporan_rutin',
            //     'biaya.verifikasi AS verifikasi_rutin',
            //     'alokasi_dana.alokasi_dana_lpu AS alokasi_dana_lpu',
            // ])
            //     ->leftJoin('produksi', 'produksi.id_kpc', '=', 'kpc.id')
            //     ->leftJoinSub($subqueryAlokasiDana, 'alokasi_dana', function ($join) {
            //         $join->on('alokasi_dana.id_kpc', '=', 'kpc.id');
            //     })
            //     ->leftJoinSub($subqueryAtribusi, 'atribusi', function ($join) {
            //         $join->on('atribusi.id_kpc', '=', 'kpc.id');
            //     })
            //     ->leftJoinSub($subqueryBiaya, 'biaya', function ($join) {
            //         $join->on('biaya.id_kpc', '=', 'kpc.id');
            //     })
            //     ->leftJoinSub($subqueryVerifikasi, 'verifikasi', function ($join) {
            //         $join->on('verifikasi.id_kpc', '=', 'kpc.id')
            //             ->on('verifikasi.tahun_anggaran', '=', 'produksi.tahun_anggaran')
            //             ->on('verifikasi.triwulan', '=', 'produksi.triwulan');
            //     })
            //     ->leftJoinSub($subqueryVerifikasiLpu, 'verifikasi_lpu', function ($join) {
            //         $join->on('verifikasi_lpu.id_kpc', '=', 'kpc.id');
            //     })
            //     ->where('kpc.id', 11000)
            //     ->groupBy('kpc.id');
            $query = Kpc::where('kpc.id_kprk', 11000)
                ->select([
                    'kpc.id AS id_kpc',
                    'kpc.nama AS nama_kpc',
                    'verifikasi.pelaporan_outgoing AS pelaporan_outgoing',
                    'verifikasi.pelaporan_incoming AS pelaporan_incoming',
                    'verifikasi.verifikasi_incoming AS verifikasi_incoming',
                    'verifikasi.pelaporan_sisa_layanan AS pelaporan_sisa_layanan',
                    'verifikasi.verifikasi_outgoing AS verifikasi_outgoing',
                    'verifikasi.verifikasi_sisa_layanan AS verifikasi_sisa_layanan',
                    'atribusi.pelaporan AS pelaporan_atribusi',
                    'atribusi.verifikasi AS verifikasi_atribusi',
                    'biaya.pelaporan AS pelaporan_rutin',
                    'biaya.verifikasi AS verifikasi_rutin',
                    'alokasi_dana.alokasi_dana_lpu AS alokasi_dana_lpu',
                ])
                ->leftJoin('produksi', function ($join) {
                    $join->on('produksi.id_kpc', '=', 'kpc.id')
                        ->where('produksi.tahun_anggaran', 2023)
                        ->where('produksi.triwulan', 1);
                })
                ->leftJoin(DB::raw('(SELECT alokasi_dana.id_kpc AS id_kpc, alokasi_dana.tahun as tahun, alokasi_dana.triwulan as triwulan,
                    SUM(alokasi_dana_lpu) AS alokasi_dana_lpu
                FROM alokasi_dana
                WHERE alokasi_dana.tahun = 2023 AND alokasi_dana.triwulan = 1
                GROUP BY id_kpc
                ) AS alokasi_dana'), 'alokasi_dana.id_kpc', '=', 'kpc.id')
                ->leftJoin(DB::raw('(SELECT biaya_atribusi.id_kprk AS id_kprk, biaya_atribusi.tahun_anggaran as tahun, biaya_atribusi.triwulan as triwulan,
                SUM(biaya_atribusi_detail.pelaporan) AS pelaporan,
                SUM(biaya_atribusi_detail.verifikasi) AS verifikasi
            FROM biaya_atribusi_detail
            LEFT JOIN biaya_atribusi ON biaya_atribusi.id = biaya_atribusi_detail.id_biaya_atribusi
            WHERE biaya_atribusi.tahun_anggaran = 2023 AND biaya_atribusi.triwulan = 1
            GROUP BY biaya_atribusi.id_kprk
            ) AS atribusi'), 'atribusi.id_kprk', '=', 'kpc.id_kprk')

                ->leftJoin(DB::raw('(SELECT verifikasi_biaya_rutin.id_kpc AS id_kpc, verifikasi_biaya_rutin.tahun as tahun, verifikasi_biaya_rutin.triwulan as triwulan,
                    SUM(pelaporan) AS pelaporan,
                    SUM(verifikasi) AS verifikasi
                FROM verifikasi_biaya_rutin_detail
                LEFT JOIN verifikasi_biaya_rutin ON verifikasi_biaya_rutin.id = verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin
                WHERE verifikasi_biaya_rutin.tahun = 2023 AND verifikasi_biaya_rutin.triwulan = 1
                GROUP BY id_kpc
                ) AS biaya'), 'biaya.id_kpc', '=', 'kpc.id')
                ->leftJoin(DB::raw('(SELECT produksi.id_kpc as id_kpc, produksi.tahun_anggaran as tahun_anggaran, produksi.triwulan as triwulan,
                    SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_outgoing_lpu,
                    SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_incoming_lpu,
                    SUM(IF(jenis_produksi="SISA LAYANAN", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_sisa_layanan_lpu,
                    SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_outgoing_lpu,
                    SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_incoming_lpu,
                    SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_sisa_layanan_lpu
                FROM produksi_detail
                LEFT JOIN produksi ON produksi.id=produksi_detail.id_produksi
                WHERE (kategori_produksi="LAYANAN POS UNIVERSAL" OR kategori_produksi="LAYANAN POS KOMERSIL")
                    AND produksi.tahun_anggaran = 2023 AND produksi.triwulan = 1
                GROUP BY id_kpc
                ) as verifikasi_lpu'), 'verifikasi_lpu.id_kpc', '=', 'kpc.id')
                ->leftJoin(DB::raw('(SELECT produksi.id_kpc as id_kpc, produksi.tahun_anggaran as tahun_anggaran, produksi.triwulan as triwulan,
                    SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_outgoing,
                    SUM(IF(jenis_produksi="SISA LAYANAN", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_sisa_layanan,
                    SUM(IF(jenis_produksi="SISA LAYANAN", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_sisa_layanan,
                    SUM(IF(jenis_produksi="PENERIMAAN/OUTGOING", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_outgoing,
                    SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (pelaporan*rtarif*(tpkirim/100)), 0)) AS pelaporan_incoming,
                    SUM(IF(jenis_produksi="PENGELUARAN/INCOMING", (verifikasi*rtarif*(tpkirim/100)), 0)) AS verifikasi_incoming
                FROM produksi_detail
                LEFT JOIN produksi ON produksi.id=produksi_detail.id_produksi
                WHERE produksi.tahun_anggaran = 2023 AND produksi.triwulan = 1
                GROUP BY id_kpc
                ) as verifikasi'), 'verifikasi.id_kpc', '=', 'kpc.id')
                ->groupBy('kpc.id');
            $result = $query->get();
            // dd($results);
            $data = [];
            foreach ($result as $item) {
                $pelaporan_kprk_biaya = $item->pelaporan_atribusi + $item->pelaporan_rutin;
                $pelaporan_transfer_pricing = $item->pelaporan_outgoing_lpu + $item->pelaporan_incoming_lpu;
                $total_laporan_kprk = $item->pelaporan_sisa_layanan_lpu + $pelaporan_transfer_pricing;
                /* Verifikasi */
                $hasil_biaya = $item->verifikasi_atribusi + $item->verifikasi_rutin;
                $hasil_transfer_pricing = $item->verifikasi_outgoing_lpu + $item->verifikasi_incoming_lpu;
                $total_hasil_verifikasi = $item->verifikasi_sisa_layanan_lpu + $hasil_transfer_pricing;
                /* Deviasi */
                $deviasi_biaya = $pelaporan_kprk_biaya - $hasil_biaya;
                $deviasi_sisa = $item->pelaporan_sisa_layanan_lpu - $item->verifikasi_sisa_layanan_lpu;
                $deviasi_transfer = $pelaporan_transfer_pricing - $hasil_transfer_pricing;
                $deviasi_produksi = $total_laporan_kprk - $total_hasil_verifikasi;
                $deviasi_akhir = $total_hasil_verifikasi - $hasil_biaya;
                $data[] = [
                    'id_kprk' => $item->id_kprk,
                    'nama_kpc' => $item->nama_kpc,
                    'alokasi_dana_lpu' => ($item->alokasi_dana_lpu ? $item->alokasi_dana_lpu : 0),
                    'pelaporan_kprk_biaya' => ($pelaporan_kprk_biaya ? $pelaporan_kprk_biaya : 0),
                    'pelaporan_sisa_layanan' => ($item->pelaporan_sisa_layanan ? $item->pelaporan_sisa_layanan : 0),
                    'pelaporan_transfer_pricing' => ($pelaporan_transfer_pricing ? $pelaporan_transfer_pricing : 0),
                    'total_laporan_kprk' => ($total_laporan_kprk ? $total_laporan_kprk : 0),
                    'hasil_biaya' => ($hasil_biaya ? $hasil_biaya : 0),
                    'verifikasi_sisa_layanan' => ($item->verifikasi_sisa_layanan ? $item->verifikasi_sisa_layanan : 0),
                    'hasil_transfer_pricing' => ($hasil_transfer_pricing ? $hasil_transfer_pricing : 0),
                    'total_hasil_verifikasi' => ($total_hasil_verifikasi ? $total_hasil_verifikasi : 0),
                    'deviasi_biaya' => ($deviasi_biaya ? $deviasi_biaya : 0),
                    'deviasi_sisa' => ($deviasi_sisa ? $deviasi_sisa : 0),
                    'deviasi_transfer' => ($deviasi_transfer ? $deviasi_transfer : 0),
                    'deviasi_produksi' => ($deviasi_produksi ? $deviasi_produksi : 0),
                    'deviasi_akhir' => ($deviasi_akhir ? $deviasi_akhir : 0),

                ];
            }

            return Excel::download(new LaporanKertasKerjaVerifikasiDetailExport($data), 'template-laporan_kertas_kerja_verifikasi_detail.xlsx');
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

}
