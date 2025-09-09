<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\Kprk;
use App\Models\Produksi;
use App\Models\UserLog;
use App\Models\LockVerifikasi;
use App\Models\ProduksiDetail;
use App\Models\Regional;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class VerifikasiProduksiController extends Controller
{
    // public function getPerTahun(Request $request)
    // {
    //     try {

    //         $validator = Validator::make($request->all(), [
    //             'tahun' => 'nullable|numeric', // Menyatakan bahwa tahun_anggaran bersifat opsional dan harus berupa angka
    //             'triwulan' => 'nullable|numeric|in:1,2,3,4', // Menyatakan bahwa triwulan bersifat opsional, harus berupa angka, dan nilainya hanya boleh 1, 2, 3, atau 4
    //             'status' => 'nullable|string|in:7,9', // Menyatakan bahwa status bersifat opsional, harus berupa string, dan nilainya hanya boleh "aktif" atau "nonaktif"
    //         ]);
    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => $validator->errors(),
    //                 'error_code' => 'INPUT_VALIDATION_ERROR',
    //             ], 422);
    //         }
    //         if (!$request->filled('tahun')) {
    //             return response()->json([
    //                 'status' => 'SUCCESS',
    //                 'offset' => 0,
    //                 'limit' => 0,
    //                 'order' => $request->get('order', ''),
    //                 'search' => $request->get('search', ''),
    //                 'total_data' => 0,
    //                 'grand_total' => 'Rp 0',
    //                 'data' => [],
    //             ]);
    //         }
    //         $offset = request()->get('offset', 0);
    //         $limit = request()->get('limit', 100);
    //         $search = request()->get('search', '');
    //         $getOrder = request()->get('order', '');
    //         $tahun_anggaran = request()->get('tahun', '');
    //         $triwulan = request()->get('triwulan', '');
    //         $status = request()->get('status', '');

    //         $defaultOrder = $getOrder ? $getOrder : "regional.nama ASC";
    //         $orderMappings = [
    //             'namaASC' => 'regional.nama ASC',
    //             'namaDESC' => 'regional.nama DESC',
    //             'triwulanASC' => 'produksi.triwulan ASC',
    //             'triwulanDESC' => 'produksi.triwulan DESC',
    //             'tahunASC' => 'produksi.tahun_anggaran ASC',
    //             'tahunDESC' => 'produksi.tahun_anggaran DESC',
    //         ];

    //         // Set the order based on the mapping or use the default order if not found
    //         $order = $orderMappings[$getOrder] ?? $defaultOrder;
    //         // Validation rules for input parameters
    //         $validOrderValues = implode(',', array_keys($orderMappings));
    //         $rules = [
    //             'offset' => 'integer|min:0',
    //             'limit' => 'integer|min:1',
    //             'order' => "in:$validOrderValues",
    //         ];

    //         $validator = Validator::make([
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //         ], $rules);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'ERROR',
    //                 'message' => 'Invalid input parameters',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         $produksiQuery = Produksi::orderByRaw($order)
    //             ->select(
    //                 'produksi.id_regional',
    //                 'produksi.triwulan',
    //                 'produksi.tahun_anggaran',
    //                 'regional.nama as nama_regional',
    //                 DB::raw('SUM(produksi.total_lpu) as total_lpu'),
    //                 DB::raw('SUM(produksi.total_lpu_prognosa) as total_lpu_prognosa'),
    //                 DB::raw('SUM(produksi.total_lpk) as total_lpk'),
    //                 DB::raw('SUM(produksi.total_lpk_prognosa) as total_lpk_prognosa'),
    //                 DB::raw('SUM(produksi.total_lbf) as total_lbf'),
    //                 DB::raw('SUM(produksi.total_lbf_prognosa) as total_lbf_prognosa'),
    //                 // DB::raw('SUM(produksi_detail.verifikasi) as total_produksi')
    //             )
    //             ->join('regional', 'produksi.id_regional', '=', 'regional.id')
    //         // ->join('produksi_detail', 'produksi.id', '=', 'produksi_detail.id_produksi')
    //             ->groupBy('produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran')
    //             ->offset($offset)
    //             ->limit($limit);

    //             if ($search !== '') {
    //                 $produksiQuery->where(function ($query) use ($search) {
    //                     $query->where('regional.nama', 'like', "%$search%")
    //                         ->orWhere('produksi.triwulan', 'like', "%$search%")
    //                         ->orWhere('produksi.tahun_anggaran', 'like', "%$search%");
    //                 });
    //             }
    //         // Menambahkan kondisi WHERE berdasarkan variabel $tahun_anggaran, $triwulan, dan $status
    //         if ($tahun_anggaran !== '') {
    //             $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
    //         }
    //         if ($triwulan !== '') {
    //             $produksiQuery->where('produksi.triwulan', $triwulan);
    //         }
    //         if ($status !== '') {
    //             $produksiQuery->where('produksi.id_status', $status);
    //         }

    //         $produksi = $produksiQuery->get();
    //         // dd($produksi);
    //         $grand_total = $produksi->sum('total_lpu') + $produksi->sum('total_lpk') + $produksi->sum('total_lbf');
    //        $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');

    //         foreach ($produksi as $item) {
    //             $total_produksi = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //                 ->where('produksi.id_regional', $item->id_regional)
    //                 ->where('produksi.triwulan', $item->triwulan)
    //                 ->where('produksi.tahun_anggaran', $item->tahun_anggaran)
    //                 ->groupBy('produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran')->SUM('produksi_detail.pelaporan');
    //             // Format total produksi
    //             $item->total_produksi = "Rp " . number_format(round($total_produksi),0, '', '.');

    //             // Ambil Produksi dengan kriteria tertentu
    //             $getProduksi = Produksi::where('tahun_anggaran', $item->tahun_anggaran)
    //                 ->where('id_regional', $item->id_regional)
    //                 ->where('triwulan', $item->triwulan)
    //                 ->get();

    //             $statusId = 7;

    //             foreach ($getProduksi as $produksiStatus) {
    //                 // Pelaporan tidak sama dengan 0 atau 0.00 dan verifikasi adalah 0.00 maka status 7
    //                 if ($produksiStatus->pelaporan != 0 && $produksiStatus->pelaporan != 0.00 && $produksiStatus->verifikasi == 0.00) {
    //                     $statusId = 7;
    //                     break; // keluar dari loop jika status 7 ditemukan
    //                 }
    //                 // Pelaporan sama dengan 0 atau 0.00 dan verifikasi adalah 0.00 maka status 7
    //                 elseif (($produksiStatus->pelaporan == 0 || $produksiStatus->pelaporan == 0.00) && $produksiStatus->verifikasi == 0.00) {
    //                     $statusId = 7;
    //                 }
    //                 // Pelaporan tidak sama dengan 0 atau 0.00 dan verifikasi tidak sama dengan 0.00 maka status 9
    //                 elseif ($produksiStatus->pelaporan != 0 && $produksiStatus->pelaporan != 0.00 && $produksiStatus->verifikasi != 0.00) {
    //                     $statusId = 9;
    //                     break; // keluar dari loop jika status 9 ditemukan
    //                 }
    //             }

    //             // Ambil status berdasarkan hasil pengecekan
    //             $status = Status::firstWhere('id', $statusId);

    //             // Atur status pada item saat ini
    //             $item->status = $status->nama;
    //         }

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'grand_total' => $grand_total,
    //             'data' => $produksi,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }
    //     public function getPerTahun(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'tahun' => 'nullable|numeric',
    //             'triwulan' => 'nullable|numeric|in:1,2,3,4',
    //             'status' => 'nullable|string|in:7,9',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => $validator->errors(),
    //                 'error_code' => 'INPUT_VALIDATION_ERROR',
    //             ], 422);
    //         }

    //         $offset = $request->get('offset', 0);
    //         $limit = $request->get('limit', 100);
    //         $search = $request->get('search', '');
    //         $getOrder = $request->get('order', '');
    //         $tahun_anggaran = $request->get('tahun', '');
    //         $triwulan = $request->get('triwulan', '');
    //         $status = $request->get('status', '');

    //         $defaultOrder = $getOrder ? $getOrder : "regional.nama ASC";
    //         $orderMappings = [
    //             'namaASC' => 'regional.nama ASC',
    //             'namaDESC' => 'regional.nama DESC',
    //             'triwulanASC' => 'produksi.triwulan ASC',
    //             'triwulanDESC' => 'produksi.triwulan DESC',
    //             'tahunASC' => 'produksi.tahun_anggaran ASC',
    //             'tahunDESC' => 'produksi.tahun_anggaran DESC',
    //         ];
    //         $order = $orderMappings[$getOrder] ?? $defaultOrder;

    //         $cacheKey = "getPerTahun_{$offset}_{$limit}_{$search}_{$getOrder}_{$tahun_anggaran}_{$triwulan}_{$status}";

    //         // Cache the result for 10 minutes
    //         $response = Cache::remember($cacheKey, 86400, function () use (
    //             $offset, $limit, $order, $search, $tahun_anggaran, $triwulan, $status
    //         ) {
    //             $produksiQuery = Produksi::orderByRaw($order)
    //                 ->select(
    //                     'produksi.id_regional',
    //                     'produksi.triwulan',
    //                     'produksi.tahun_anggaran',
    //                     'regional.nama as nama_regional',
    //                     DB::raw('SUM(produksi.total_lpu) as total_lpu'),
    //                     DB::raw('SUM(produksi.total_lpu_prognosa) as total_lpu_prognosa'),
    //                     DB::raw('SUM(produksi.total_lpk) as total_lpk'),
    //                     DB::raw('SUM(produksi.total_lpk_prognosa) as total_lpk_prognosa'),
    //                     DB::raw('SUM(produksi.total_lbf) as total_lbf'),
    //                     DB::raw('SUM(produksi.total_lbf_prognosa) as total_lbf_prognosa')
    //                 )
    //                 ->join('regional', 'produksi.id_regional', '=', 'regional.id')
    //                 ->groupBy('produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran')
    //                 ->offset($offset)
    //                 ->limit($limit);

    //             if ($search !== '') {
    //                 $produksiQuery->where(function ($query) use ($search) {
    //                     $query->where('regional.nama', 'like', "%$search%")
    //                         ->orWhere('produksi.triwulan', 'like', "%$search%")
    //                         ->orWhere('produksi.tahun_anggaran', 'like', "%$search%");
    //                 });
    //             }

    //             if ($tahun_anggaran !== '') {
    //                 $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
    //             }
    //             if ($triwulan !== '') {
    //                 $produksiQuery->where('produksi.triwulan', $triwulan);
    //             }
    //             if ($status !== '') {
    //                 $produksiQuery->where('produksi.id_status', $status);
    //             }

    //             $produksi = $produksiQuery->get();

    //             $grand_total = $produksi->sum('total_lpu') + $produksi->sum('total_lpk') + $produksi->sum('total_lbf');
    //             $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');

    //             foreach ($produksi as $item) {
    //                 $total_produksi = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //                     ->where('produksi.id_regional', $item->id_regional)
    //                     ->where('produksi.triwulan', $item->triwulan)
    //                     ->where('produksi.tahun_anggaran', $item->tahun_anggaran)
    //                     ->groupBy('produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran')
    //                     ->sum('produksi_detail.pelaporan');
    //                 $item->total_produksi = "Rp " . number_format(round($total_produksi), 0, '', '.');

    //                 $getProduksi = Produksi::where('tahun_anggaran', $item->tahun_anggaran)
    //                     ->where('id_regional', $item->id_regional)
    //                     ->where('triwulan', $item->triwulan)
    //                     ->get();

    //                 $statusId = 7;
    //                 foreach ($getProduksi as $produksiStatus) {
    //                     if ($produksiStatus->pelaporan != 0 && $produksiStatus->verifikasi == 0.00) {
    //                         $statusId = 7;
    //                         break;
    //                     } elseif (($produksiStatus->pelaporan == 0 || $produksiStatus->pelaporan == 0.00) && $produksiStatus->verifikasi == 0.00) {
    //                         $statusId = 7;
    //                     } elseif ($produksiStatus->pelaporan != 0 && $produksiStatus->verifikasi != 0.00) {
    //                         $statusId = 9;
    //                         break;
    //                     }
    //                 }

    //                 $status = Status::firstWhere('id', $statusId);
    //                 $item->status = $status->nama;
    //             }

    //             return [
    //                 'grand_total' => $grand_total,
    //                 'data' => $produksi,
    //             ];
    //         });

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'grand_total' => $response['grand_total'],
    //             'data' => $response['data'],
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }
    // use Illuminate\Support\Facades\Cache;

    public function getPerTahun(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'status' => 'nullable|string|in:7,9',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            if (!$request->filled('tahun')) {
                return response()->json([
                    'status' => 'SUCCESS',
                    'offset' => 0,
                    'limit' => 0,
                    'order' => $request->get('order', ''),
                    'search' => $request->get('search', ''),
                    'total_data' => 0,
                    'grand_total' => 'Rp 0',
                    'data' => [],
                ]);
            }

            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 100);
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $tahun_anggaran = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');
            $status = $request->get('status', '');

            $orderMappings = [
                'namaASC' => 'regional.nama ASC',
                'namaDESC' => 'regional.nama DESC',
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
            ];
            $order = $orderMappings[$getOrder] ?? 'regional.nama ASC';

            $cacheKey = 'getPerTahun_' . md5(json_encode([
                'tahun' => $tahun_anggaran,
                'triwulan' => $triwulan,
                'search' => $search,
                'order' => $getOrder,
            ]));
            $cacheDuration = 86400; // 1 day

            $response = Cache::remember($cacheKey, $cacheDuration, function () use ($order, $search, $tahun_anggaran, $triwulan) {
                $subDetail = DB::table('produksi_detail')
                    ->select(
                        'id_produksi',
                        DB::raw('SUM(pelaporan) as pelaporan'),
                        DB::raw('SUM(verifikasi) as verifikasi')
                    )
                    ->groupBy('id_produksi');

                $produksiQuery = DB::table('produksi')
                    ->select(
                        'produksi.id_regional',
                        'produksi.triwulan',
                        'produksi.tahun_anggaran',
                        'regional.nama as nama_regional',
                        DB::raw('SUM(produksi.total_lpu) as total_lpu'),
                        DB::raw('SUM(produksi.total_lpu_prognosa) as total_lpu_prognosa'),
                        DB::raw('SUM(produksi.total_lpk) as total_lpk'),
                        DB::raw('SUM(produksi.total_lpk_prognosa) as total_lpk_prognosa'),
                        DB::raw('SUM(produksi.total_lbf) as total_lbf'),
                        DB::raw('SUM(produksi.total_lbf_prognosa) as total_lbf_prognosa'),
                        DB::raw("
                        CASE
                            WHEN SUM(pd.pelaporan) != 0 AND SUM(pd.verifikasi) = 0 THEN '7'
                            WHEN SUM(pd.pelaporan) = 0 AND SUM(pd.verifikasi) = 0 THEN '7'
                            WHEN SUM(pd.pelaporan) != 0 AND SUM(pd.verifikasi) != 0 THEN '9'
                            ELSE 'Unknown'
                        END as status_id
                    "),
                        DB::raw("SUM(pd.pelaporan) as total_produksi")
                    )
                    ->join('regional', 'produksi.id_regional', '=', 'regional.id')
                    ->leftJoinSub($subDetail, 'pd', function ($join) {
                        $join->on('produksi.id', '=', 'pd.id_produksi');
                    })
                    ->groupBy('produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama')
                    ->orderByRaw($order);

                if ($search !== '') {
                    $produksiQuery->where(function ($query) use ($search) {
                        $query->where('regional.nama', 'like', "%$search%")
                            ->orWhere('produksi.triwulan', 'like', "%$search%")
                            ->orWhere('produksi.tahun_anggaran', 'like', "%$search%");
                    });
                }

                if ($tahun_anggaran !== '') {
                    $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
                }

                if ($triwulan !== '') {
                    $produksiQuery->where('produksi.triwulan', $triwulan);
                }

                $produksi = collect($produksiQuery->get());

                $produksi = $produksi->map(function ($item) {
                    $item->total_produksi = "Rp " . number_format(round($item->total_produksi), 0, '', '.');
                    return $item;
                });

                $grand_total = $produksi->sum(function ($item) {
                    // Karena total_produksi sudah diformat string, hitung ulang dari total_lpu + total_lpk + total_lbf
                    return $item->total_lpu + $item->total_lpk + $item->total_lbf;
                });
                $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');

                return [
                    'grand_total' => $grand_total,
                    'data' => $produksi,
                ];
            });

            // Filter by status after query (instead of using HAVING)
            $dataFiltered = $status !== ''
                ? $response['data']->filter(fn($item) => $item->status_id == $status)->values()
                : $response['data'];

            $paginatedData = $dataFiltered->slice($offset, $limit)->values();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'grand_total' => $response['grand_total'],
                'data' => $paginatedData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    // public function getPerRegional(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'tahun' => 'nullable|numeric',
    //             'triwulan' => 'nullable|numeric|in:1,2,3,4',
    //             'id_regional' => 'nullable|numeric|exists:regional,id',
    //             'status' => 'nullable|string|in:7,9',
    //         ]);
    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => $validator->errors(),
    //                 'error_code' => 'INPUT_VALIDATION_ERROR',
    //             ], 422);
    //         }
    //         $offset = request()->get('offset', 0);
    //         $limit = request()->get('limit', 100);
    //         $search = request()->get('search', '');
    //         $getOrder = request()->get('order', '');
    //         $id_regional = request()->get('id_regional', '');
    //         $tahun_anggaran = request()->get('tahun', '');
    //         $triwulan = request()->get('triwulan', '');
    //         $status = request()->get('status', '');
    //         $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
    //         $orderMappings = [
    //             'namaASC' => 'kprk.nama ASC',
    //             'namaDESC' => 'kprk.nama DESC',
    //             'triwulanASC' => 'produksi.triwulan ASC',
    //             'triwulanDESC' => 'produksi.triwulan DESC',
    //             'tahunASC' => 'produksi.tahun_anggaran ASC',
    //             'tahunDESC' => 'produksi.tahun_anggaran DESC',
    //         ];

    //         // Set the order based on the mapping or use the default order if not found
    //         $order = $orderMappings[$getOrder] ?? $defaultOrder;
    //         // Validation rules for input parameters
    //         $validOrderValues = implode(',', array_keys($orderMappings));
    //         $rules = [
    //             'offset' => 'integer|min:0',
    //             'limit' => 'integer|min:1',
    //             'order' => "in:$validOrderValues",
    //         ];

    //         $validator = Validator::make([
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //         ], $rules);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'ERROR',
    //                 'message' => 'Invalid input parameters',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }
    //         $produksiQuery = ProduksiDetail::orderByRaw($order)
    //             ->select('produksi.id', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama as nama_regional', 'regional.id as id_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', DB::raw('SUM(produksi_detail.pelaporan) as total_produksi'))
    //             ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //             ->join('kprk', 'produksi.id_kprk', '=', 'kprk.id')
    //             ->join('regional', 'produksi.id_regional', '=', 'regional.id')
    //             ->groupBy('kprk.id', 'produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama')
    //             ->offset($offset)
    //             ->limit($limit);

    //         if ($search !== '') {
    //             $produksiQuery->where(function ($query) use ($search) {
    //                 $query->where('regional.nama', 'like', "%$search%")
    //                     ->orWhere('produksi.triwulan', 'like', "%$search%")
    //                     ->orWhere('produksi.tahun_anggaran', 'like', "%$search%")
    //                     -orWhere('kprk.nama', 'like', "%$search%");
    //             });
    //         }
    //         if ($id_regional !== '') {
    //             $produksiQuery->where('produksi.id_regional', $id_regional);
    //         }
    //         if ($tahun_anggaran !== '') {
    //             $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
    //         }

    //         if ($triwulan !== '') {
    //             $produksiQuery->where('produksi.triwulan', $triwulan);
    //         }

    //         if ($status !== '') {
    //             $produksiQuery->where('produksi.id_status', $status);
    //         }

    //         $produksi = $produksiQuery->get();

    //         foreach ($produksi as $item) {
    //             // Format total produksi
    //             $item->total_produksi = "Rp " . number_format(round($item->total_produksi),0, '', '.');

    //             $getProduksi = ProduksiDetail::where('id_produksi', $item->id)
    //                 ->where('pelaporan', '<>', 0.00)
    //                 ->where('verifikasi', 0.00)
    //                 ->first();

    //             $statusId = 9; // Default status 9

    //             if ($getProduksi) {
    //                 $statusId = 7;
    //             }

    //             // Ambil status berdasarkan hasil pengecekan
    //             $status = Status::firstWhere('id', $statusId);

    //             // Atur status pada item saat ini
    //             $item->status = $status->nama;
    //         }

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'data' => $produksi,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }
    // use Illuminate\Support\Facades\Cache;

    public function getPerRegional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'status' => 'nullable|string|in:7,9',
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
            $tahun_anggaran = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');
            $status = $request->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
            ];
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            // Cache key
            $cacheKey = "getPerRegional_{$offset}_{$limit}_{$search}_{$getOrder}_{$id_regional}_{$tahun_anggaran}_{$triwulan}_{$status}";
            $cacheDuration = 1440; // Cache duration in minutes (1 day)

            $response = Cache::remember($cacheKey, $cacheDuration, function () use (
                $offset,
                $limit,
                $order,
                $search,
                $id_regional,
                $tahun_anggaran,
                $triwulan,
                $status
            ) {
                $produksiQuery = ProduksiDetail::select(
                    'produksi.id',
                    'produksi.triwulan',
                    'produksi.tahun_anggaran',
                    'regional.nama as nama_regional',
                    'regional.id as id_regional',
                    'kprk.id as id_kcu',
                    'kprk.nama as nama_kcu',
                    DB::raw('SUM(produksi_detail.pelaporan) as total_produksi'),
                    DB::raw("
                    CASE
                        WHEN SUM(produksi_detail.pelaporan) != 0 AND SUM(produksi_detail.verifikasi) = 0 THEN '7'
                        ELSE '9'
                    END as status_id
                ")
                )
                    ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                    ->join('kprk', 'produksi.id_kprk', '=', 'kprk.id')
                    ->join('regional', 'produksi.id_regional', '=', 'regional.id')
                    ->groupBy('kprk.id', 'produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama')
                    ->orderByRaw($order)
                    ->offset($offset)
                    ->limit($limit);

                if ($search !== '') {
                    $produksiQuery->where(function ($query) use ($search) {
                        $query->where('regional.nama', 'like', "%$search%")
                            ->orWhere('produksi.triwulan', 'like', "%$search%")
                            ->orWhere('produksi.tahun_anggaran', 'like', "%$search%")
                            ->orWhere('kprk.nama', 'like', "%$search%");
                    });
                }
                if ($id_regional !== '') {
                    $produksiQuery->where('produksi.id_regional', $id_regional);
                }
                if ($tahun_anggaran !== '') {
                    $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
                }
                if ($triwulan !== '') {
                    $produksiQuery->where('produksi.triwulan', $triwulan);
                }
                if ($status !== '') {
                    $produksiQuery->having('status_id', $status);
                }

                $produksi = $produksiQuery->get();

                // Format the total produksi for each row
                foreach ($produksi as $item) {
                    $item->total_produksi = "Rp " . number_format(round($item->total_produksi), 0, '', '.');
                }

                return $produksi;
            });

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function getPerKCU(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_kcu' => 'nullable|numeric|exists:kprk,id',
                'status' => 'nullable|string|in:7,9',
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
            $id_kcu = $request->get('id_kcu', '');
            $tahun_anggaran = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');
            $statusFilter = $request->get('status', '');

            $orderMappings = [
                'namakpcASC' => 'kpc.nama ASC',
                'namakpcDESC' => 'kpc.nama DESC',
                'namakcuASC' => 'kprk.nama ASC',
                'namakcuDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
            ];

            $order = $orderMappings[$getOrder] ?? 'produksi.id ASC';

            $produksiQuery = ProduksiDetail::select(
                'produksi.id as produksi_id',
                'produksi.triwulan',
                'produksi.tahun_anggaran',
                'produksi.id_regional',
                'produksi.status_kprk',
                'produksi.status_regional',
                'produksi.id_kprk as id_kcu',
                'produksi.id_kpc as id_kpc',
                DB::raw('SUM(produksi_detail.pelaporan) as total_produksi')
            )
                ->leftJoin('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->groupBy('produksi.id_kpc', 'produksi.triwulan', 'produksi.tahun_anggaran')
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $produksiQuery->where(function ($query) use ($search) {
                    $query->where('produksi.triwulan', 'like', "%$search%")
                        ->orWhere('produksi.tahun_anggaran', 'like', "%$search%");
                });
            }

            if ($id_kcu !== '') {
                $produksiQuery->where('produksi.id_kprk', $id_kcu);
            }

            if ($tahun_anggaran !== '') {
                $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
            }

            if ($triwulan !== '') {
                $produksiQuery->where('produksi.triwulan', $triwulan);
            }

            if ($statusFilter !== '') {
                $produksiQuery->where('produksi.status_kprk', $statusFilter);
            }

            $produksi = $produksiQuery->get();

            // Ambil ID terkait secara bulk
            $regionalIds = $produksi->pluck('id_regional')->unique()->toArray();
            $kcuIds = $produksi->pluck('id_kcu')->unique()->toArray();
            $kpcIds = $produksi->pluck('id_kpc')->unique()->toArray();
            $produksiIds = $produksi->pluck('produksi_id')->toArray();

            $regionals = Regional::whereIn('id', $regionalIds)->get()->keyBy('id');
            $kprs = Kprk::whereIn('id', $kcuIds)->get()->keyBy('id');
            $kpcs = Kpc::whereIn('id', $kpcIds)->get()->keyBy('id');

            $produksiDetailByProduksi = ProduksiDetail::whereIn('id_produksi', $produksiIds)
                ->where('pelaporan', '<>', 0.00)
                ->where('verifikasi', 0.00)
                ->get()
                ->groupBy('id_produksi');


            foreach ($produksi as $item) {
                $item->total_produksi = "Rp " . number_format(round($item->total_produksi), 0, '', '.');

                $item->nama_regional = $regionals[$item->id_regional]->nama ?? '';
                $item->nama_kcu = $kprs[$item->id_kcu]->nama ?? '';
                $item->nama_kpc = $kpcs[$item->id_kpc]->nama ?? '';

                // $statusId = 9;
                // if (isset($produksiDetailByProduksi[$item->produksi_id]) && $produksiDetailByProduksi[$item->produksi_id]->isNotEmpty()) {
                //     $statusId = 7;
                // }

                $statusList = Status::whereIn('id', [7, 9])->get()->keyBy('id');


                $item->status = $statusList[$item->status_kprk]->nama;
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $produksi,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getPerKPC(Request $request)
    {
        try {
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 100);
            $getOrder = $request->get('order', '');
            $id_produksi = $request->get('id_produksi', '');
            $id_kcu = $request->get('id_kcu', '');
            $id_kpc = $request->get('id_kpc', '');

            // Validasi awal
            $validator = Validator::make($request->all(), [
                'id_produksi' => 'required|string|exists:produksi,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|numeric|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            // Sorting
            $defaultOrder = "produksi_detail.kategori_produksi ASC";
            $orderMappings = [
                'kodeproduksiASC' => 'rekening_produksi.kodeproduksi ASC',
                'kodeproduksiDESC' => 'rekening_produksi.kodeproduksi DESC',
                'namaASC' => 'rekening_produksi.nama ASC',
                'namaDESC' => 'rekening_produksi.nama DESC',
            ];
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            // Validasi limit, offset, order
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

            // Ambil seluruh data produksi_detail sekaligus
            $produksiDetails = ProduksiDetail::select(
                'produksi.id as id_produksi',
                'produksi.triwulan',
                'produksi.tahun_anggaran',
                'produksi_detail.id as id_produksi_detail',
                'produksi_detail.kode_rekening',
                'rekening_produksi.nama as nama_rekening',
                'produksi_detail.nama_bulan',
                'produksi_detail.kategori_produksi',
                'produksi_detail.jenis_produksi',
                'produksi_detail.keterangan',
                'produksi_detail.pelaporan',
                'produksi_detail.verifikasi'
            )
                ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->join('rekening_produksi', 'produksi_detail.kode_rekening', '=', 'rekening_produksi.kode_rekening')
                ->where('produksi.id', $id_produksi)
                ->where('produksi.id_kprk', $id_kcu)
                ->where('produksi.id_kpc', $id_kpc)
                ->orderByRaw($order)
                ->get();

            if ($produksiDetails->isEmpty()) {
                return response()->json([
                    'status' => 'SUCCESS',
                    'data' => [],
                ]);
            }

            // Ambil data triwulan dan tahun
            $firstDetail = $produksiDetails->first();
            $triwulan = $firstDetail->triwulan;
            $tahunAnggaran = $firstDetail->tahun_anggaran;

            // Hitung range bulan berdasarkan triwulan
            $startBulan = (($triwulan - 1) * 3) + 1;
            $endBulan = $startBulan + 2;

            // Ambil status kunci (lock)
            $lockStatuses = LockVerifikasi::where('tahun', $tahunAnggaran)->pluck('status', 'bulan');

            $bulanIndonesia = [
                1 => 'Januari',
                2 => 'Februari',
                3 => 'Maret',
                4 => 'April',
                5 => 'Mei',
                6 => 'Juni',
                7 => 'Juli',
                8 => 'Agustus',
                9 => 'September',
                10 => 'Oktober',
                11 => 'November',
                12 => 'Desember',
            ];

            $grouped = [];

            foreach ($produksiDetails as $item) {
                $key = "{$item->kode_rekening}_{$item->jenis_produksi}_{$item->kategori_produksi}_{$item->keterangan}";
                $bulan = (int) $item->nama_bulan;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'kode_rekening' => $item->kode_rekening,
                        'nama_rekening' => $item->nama_rekening,
                        'jenis_layanan' => $item->kategori_produksi,
                        'aktifitas' => $item->jenis_produksi,
                        'produk_keterangan' => $item->keterangan,
                        'laporan' => [],
                    ];
                }

                $grouped[$key]['laporan'][$bulan] = [
                    'id_produksi_detail' => $item->id_produksi_detail,
                    'aktivitas' => $item->jenis_produksi,
                    'produk_keterangan' => $item->keterangan,
                    'jenis_layanan' => $item->kategori_produksi,
                    'bulan_string' => $bulanIndonesia[$bulan],
                    'bulan' => str_pad($bulan, 2, '0', STR_PAD_LEFT),
                    'pelaporan' => 'Rp. ' . number_format((float) $item->pelaporan, 0, '', '.'),
                    'verifikasi' => 'Rp. ' . number_format((float) $item->verifikasi, 0, '', '.'),
                    'isLock' => $lockStatuses[str_pad($bulan, 2, '0', STR_PAD_LEFT)] ?? false
                ];
            }

            foreach ($grouped as &$group) {
                $laporan = $group['laporan'];
                $laporanByBulan = [];

                foreach ($laporan as $item) {
                    $laporanByBulan[$item['bulan']] = $item;
                }

                for ($bulan = $startBulan; $bulan <= $endBulan; $bulan++) {
                    $bulanKey = str_pad($bulan, 2, '0', STR_PAD_LEFT);
                    if (!isset($laporanByBulan[$bulanKey])) {
                        $laporanByBulan[$bulanKey] = [
                            'id_produksi_detail' => null,
                            'aktivitas' => $group['aktifitas'],
                            'produk_keterangan' => $group['produk_keterangan'],
                            'jenis_layanan' => $group['jenis_layanan'],
                            'bulan_string' => $bulanIndonesia[$bulan],
                            'bulan' => $bulanKey,
                            'pelaporan' => 'Rp. 0',
                            'verifikasi' => 'Rp. 0',
                            'isLock' => $lockStatuses[$bulanKey] ?? false,
                        ];
                    }
                }

                ksort($laporanByBulan);
                $group['laporan'] = array_values($laporanByBulan);
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $order,
                'id_kcu' => $id_kcu,
                'id_kpc' => $id_kpc,
                'data' => array_values($grouped),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function notSimpling(Request $request)
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $getOrder = request()->get('order', '');
            $id_produksi = request()->get('id_produksi', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $status = 10;
            $validator = Validator::make($request->all(), [
                'id_produksi' => 'required|string|exists:produksi,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|numeric|exists:kprk,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $produksi = Produksi::where('id', $request->id_produksi)
                ->where('id_kprk', $request->id_kcu)
                ->where('id_kpc', $request->id_kpc)->first();
            $produksi->update([
                'status_regional' => 10,
                'status_kprk' => 10,
            ]);
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Update Simpling Produksi',
                'modul' => 'Produksi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);

            return response()->json(['status' => 'SUCCESS', 'data' => $produksi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function submit(Request $request)
    {
        try {
            $produksi = Produksi::where('id', $request->data)->first();
            $produksi->update([
                'status_regional' => 9,
                'status_kprk' => 9,
            ]);
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Update Status Produksi',
                'modul' => 'Produksi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
	    Artisan::call('cache:clear');
            return response()->json(['status' => 'SUCCESS', 'data' => $produksi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function getDetail(Request $request)
    {

        try {

            $id_produksi_detail = request()->get('id_produksi_detail', '');
            $id_produksi = request()->get('id_produksi', '');
            $kode_rekening = request()->get('kode_rekening', '');
            $bulan = request()->get('bulan', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $validator = Validator::make($request->all(), [
                'id_produksi_detail' => 'required|string|exists:produksi_detail,id',
                // 'bulan' => 'required|numeric|max:12',
                // 'kode_rekening' => 'required|numeric|exists:rekening_produksi,kode_rekening',
                // 'id_produksi' => 'required|string|exists:produksi,id',
                // 'id_kpc' => 'required|string|exists:kpc,id',
                // 'id_kcu' => 'required|string|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
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
                'Desember',
            ];

            $produksi = ProduksiDetail::select(
                'produksi_detail.id as id_produksi_detail',
                'rekening_produksi.kode_rekening',
                'rekening_produksi.nama as nama_rekening',
                'produksi.tahun_anggaran',
                'produksi_detail.keterangan as produk_keterangan',
                'produksi_detail.jenis_produksi as aktivitas',
                'produksi_detail.kategori_produksi as jenis_layanan',
                'produksi_detail.nama_bulan',
                'produksi_detail.lampiran',
                'produksi_detail.pelaporan',
                'produksi_detail.verifikasi',
                'produksi_detail.catatan_pemeriksa',
            )
                ->where('produksi_detail.id', $request->id_produksi_detail)
                ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->join('rekening_produksi', 'produksi_detail.kode_rekening', '=', 'rekening_produksi.kode_rekening')
                ->join('kprk', 'produksi.id_kprk', '=', 'kprk.id')
                ->get();

            $isLockStatus = false;
            if ($produksi) {
                foreach ($produksi as $item) {

                    $item->periode = $bulanIndonesia[$item->nama_bulan - 1];
                    $item->pelaporan = "Rp " . number_format(round($item->pelaporan), 0, '', '.');
                    $item->verifikasi = "Rp " . number_format(round($item->verifikasi), 0, '', '.');
                    $item->url_lampiran = env('ENV_CONFIG_PATH') . $item->lampiran;

                    $isLock = LockVerifikasi::where('tahun', $item->tahun_anggaran)->where('bulan', $bulan)->first();
                    if ($isLock) {
                        $isLockStatus = $isLock->status;
                    }
                }
            }
            if ($isLockStatus == true) {
                $produksi = [];
            }


            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'data' => $produksi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    // public function verifikasi(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             '*.id_produksi_detail' => 'required|string|exists:produksi_detail,id',
    //             '*.verifikasi' => 'required|string',
    //             '*.catatan_pemeriksa' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
    //         }

    //         foreach ($request->all() as $data) {
    //             $id_produksi_detail = $data['id_produksi_detail'];
    //             $verifikasi = $data['verifikasi'];
    //             $verifikasi = str_replace(['Rp.', '.'], '', $verifikasi);
    //             $verifikasi = str_replace(',', '.', $verifikasi);
    //             $verifikasiFloat = (float) $verifikasi;
    //             $verifikasiFormatted = number_format($verifikasiFloat, 0, '', '.');
    //             $catatan_pemeriksa = $data['catatan_pemeriksa'];
    //             $id_validator = Auth::user()->id;
    //             $tanggal_verifikasi = now();

    //             $produksi_detail = ProduksiDetail::find($id_produksi_detail);

    //             $produksi_detail->update([
    //                 'verifikasi' => $verifikasiFormatted,
    //                 'catatan_pemeriksa' => $catatan_pemeriksa,
    //                 'id_validator' => $id_validator,
    //                 'tgl_verifikasi' => $tanggal_verifikasi,
    //             ]);

    //             if ($produksi_detail) {
    //                 $produksi = ProduksiDetail::where('id_produksi', $produksi_detail->id_produksi)->get();
    //                 $countValid = $produksi->filter(function ($detail) {
    //                     return $detail->verifikasi != 0.00 && $detail->tgl_verifikasi !== null;
    //                 })->count();

    //                 if ($countValid === $produksi->count()) {
    //                     Produksi::where('id', $id_produksi)->update(['id_status' => 9]);
    //                 }
    //             }
    //         }

    //         return response()->json(['status' => 'SUCCESS']);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()],500);
    //     }
    // }

    public function verifikasi(Request $request)
    {
        try {
            // Validasi input dari request
            $validator = Validator::make($request->all(), [
                'data.*.id_produksi_detail' => 'required|string|exists:produksi_detail,id',
                'data.*.verifikasi' => 'required|string',
                'data.*.catatan_pemeriksa' => 'nullable|string',
            ]);

            // Cek jika validasi gagal
            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $verifikasiData = $request->input('data');
            if (!$verifikasiData || !is_array($verifikasiData) || count($verifikasiData) === 0) {
                return response()->json(['status' => 'ERROR', 'message' => 'Data kosong'], 400);
            }

            // Ambil data paling awal saja
            $data = $verifikasiData[0];

            if (!isset($data['id_produksi_detail']) || !isset($data['verifikasi'])) {
                return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], 400);
            }

            $id_produksi_detail = $data['id_produksi_detail'];
            $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
            $verifikasiFloat = round((float) $verifikasi);
            $verifikasiFormatted = (string) $verifikasiFloat;
            $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
            $id_validator = Auth::user()->id;
            $tanggal_verifikasi = now();

            $produksi_detail = ProduksiDetail::find($id_produksi_detail);

            if (!$produksi_detail) {
                return response()->json(['status' => 'ERROR', 'message' => 'Detail produksi tidak ditemukan'], 404);
            }

            $produksi_detail->update([
                'verifikasi' => $verifikasiFormatted,
                'catatan_pemeriksa' => $catatan_pemeriksa,
                'id_validator' => $id_validator,
                'tgl_verifikasi' => $tanggal_verifikasi,
            ]);

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Verifikasi Produksi',
                'modul' => 'Produksi',
                'id_user' => Auth::user(),
            ];

            UserLog::create($userLog);

            return response()->json(['status' => 'SUCCESS', 'data' => $produksi_detail]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
