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
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KertasKerjaVerifikasiController extends Controller
{


    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1|max:1000',
                'order' => 'nullable|string',
                'id_kprk' => 'nullable|numeric|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset = (int) $request->get('offset', 0);
            $limit = (int) $request->get('limit', 100);
            $getOrder = $request->get('order', '');
            $id_regional = $request->get('id_regional', null);
            $id_kprk = $request->get('id_kprk', null);
            $tahun = $request->get('tahun', null);
            $triwulan = $request->get('triwulan', null);

            $orderMappings = [
                'namaASC' => ['kprk.nama', 'ASC'],
                'namaDESC' => ['kprk.nama', 'DESC'],
                'triwulanASC' => ['biaya_atribusi.triwulan', 'ASC'],
                'triwulanDESC' => ['biaya_atribusi.triwulan', 'DESC'],
                'tahunASC' => ['biaya_atribusi.tahun_anggaran', 'ASC'],
                'tahunDESC' => ['biaya_atribusi.tahun_anggaran', 'DESC'],
            ];
            [$orderCol, $orderDir] = $orderMappings[$getOrder] ?? ['kprk.id', 'ASC'];

            // Buat cache key berdasarkan input filter
            $cacheKey = 'rekap_kprk_' . md5(json_encode($request->all()));

            // Ambil dari cache atau jalankan query
            $cachedResult = Cache::remember($cacheKey, 60 * 24, function () use (
                $triwulan,
                $tahun,
                $id_regional,
                $id_kprk,
                $offset,
                $limit,
                $orderCol,
                $orderDir
            ) {
                $subquery_vbrd = DB::table('verifikasi_biaya_rutin_detail')
                    ->select(
                        'id_kpc',
                        DB::raw('SUM(pelaporan) as pelaporan'),
                        DB::raw('SUM(verifikasi) as hasil_verifikasi')
                    )
                    ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                    ->when($triwulan, fn($q) => $q->where('verifikasi_biaya_rutin.triwulan', $triwulan))
                    ->when($tahun, fn($q) => $q->where('verifikasi_biaya_rutin.tahun', $tahun))
                    ->when($id_regional, fn($q) => $q->where('verifikasi_biaya_rutin.id_regional', $id_regional))
                    ->groupBy('id_kpc');

                $subquery_pd = DB::table('produksi_detail')
                    ->select(
                        'id_kpc',
                        DB::raw('SUM(pelaporan) as pelaporan'),
                        DB::raw('SUM(verifikasi) as hasil_verifikasi')
                    )
                    ->join('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                    ->when($triwulan, fn($q) => $q->where('produksi.triwulan', $triwulan))
                    ->when($tahun, fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                    ->when($id_regional, fn($q) => $q->where('produksi.id_regional', $id_regional))
                    ->groupBy('id_kpc');

                $baseQuery = DB::table('kprk')
                    ->select(
                        'kprk.id AS id_kprk',
                        'kprk.nama AS nama_kprk',
                        DB::raw('COALESCE(SUM(vbrd.pelaporan),0) AS hasil_pelaporan_biaya'),
                        DB::raw('COALESCE(SUM(vbrd.hasil_verifikasi),0) AS hasil_verifikasi_biaya'),
                        DB::raw('COALESCE(SUM(pd.pelaporan),0) AS hasil_pelaporan_pendapatan'),
                        DB::raw('COALESCE(SUM(pd.hasil_verifikasi),0) AS hasil_verifikasi_pendapatan')
                    )
                    ->leftJoin('kpc', 'kprk.id', '=', 'kpc.id_kprk')
                    ->leftJoinSub($subquery_vbrd, 'vbrd', fn($join) => $join->on('kpc.id', '=', 'vbrd.id_kpc'))
                    ->leftJoinSub($subquery_pd, 'pd', fn($join) => $join->on('kpc.id', '=', 'pd.id_kpc'))
                    ->when($id_regional, fn($q) => $q->where('kprk.id_regional', $id_regional))
                    ->when($id_kprk, fn($q) => $q->where('kprk.id', $id_kprk))
                    ->groupBy('kprk.id');

                $result = $baseQuery->orderBy($orderCol, $orderDir)
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

                $countQuery = DB::table('kprk')
                    ->when($id_regional, fn($q) => $q->where('kprk.id_regional', $id_regional))
                    ->when($id_kprk, fn($q) => $q->where('kprk.id', $id_kprk))
                    ->distinct('kprk.id')
                    ->count('kprk.id');

                return [
                    'data' => $result,
                    'total_data' => $countQuery,
                ];
            });

            $data = collect($cachedResult['data'])->map(fn($item) => [
                'id_kprk' => $item->id_kprk,
                'nama_kprk' => $item->nama_kprk,
                'hasil_pelaporan_biaya' => round($item->hasil_pelaporan_biaya),
                'hasil_pelaporan_pendapatan' => round($item->hasil_pelaporan_pendapatan),
                'hasil_verifikasi_biaya' => round($item->hasil_verifikasi_biaya),
                'hasil_verifikasi_pendapatan' => round($item->hasil_verifikasi_pendapatan),
                'deviasi_biaya' => round($item->hasil_pelaporan_biaya - $item->hasil_verifikasi_biaya),
                'deviasi_produksi' => round($item->hasil_pelaporan_pendapatan - $item->hasil_verifikasi_pendapatan),
                'deviasi_akhir' => round($item->hasil_verifikasi_biaya - $item->hasil_verifikasi_pendapatan),
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'total_data' => $cachedResult['total_data'],
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
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
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1|max:1000',
                'order' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset = (int) $request->get('offset', 0);
            $limit = (int) $request->get('limit', 100);
            $getOrder = $request->get('order', '');
            $id_regional = $request->get('id_regional');
            $id_kprk = $request->get('id_kprk');
            $tahun = $request->get('tahun');
            $triwulan = $request->get('triwulan');

            $orderMappings = [
                'namaASC' => ['kpc.nama', 'ASC'],
                'namaDESC' => ['kpc.nama', 'DESC'],
            ];
            [$orderCol, $orderDir] = $orderMappings[$getOrder] ?? ['kpc.id', 'ASC'];

            // Ambil data utama kpc saja
            $mainQuery = DB::table('kprk')
                ->join('kpc', 'kprk.id', '=', 'kpc.id_kprk')
                ->when($id_regional, fn($q) => $q->where('kprk.id_regional', $id_regional))
                ->when($id_kprk, fn($q) => $q->where('kprk.id', $id_kprk))
                ->select('kpc.id', 'kpc.nomor_dirian as id_kpc', 'kpc.nama as nama_kpc')
                ->orderBy($orderCol, $orderDir);

            $total = $mainQuery->count();
            $result = $mainQuery->offset($offset)->limit($limit)->get();

            $kpcIds = $result->pluck('id')->all();

            // Subquery biaya
            $biaya = DB::table('verifikasi_biaya_rutin_detail')
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) as pelaporan'),
                    DB::raw('SUM(verifikasi) as hasil_verifikasi')
                )
                ->whereIn('id_kpc', $kpcIds)
                ->when($tahun, fn($q) => $q->where('verifikasi_biaya_rutin.tahun', $tahun))
                ->when($triwulan, fn($q) => $q->where('verifikasi_biaya_rutin.triwulan', $triwulan))
                ->groupBy('id_kpc')
                ->get()
                ->keyBy('id_kpc');

            // Subquery produksi
            $produksi = DB::table('produksi_detail')
                ->join('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->select(
                    'id_kpc',
                    DB::raw('SUM(pelaporan) as pelaporan'),
                    DB::raw('SUM(verifikasi) as hasil_verifikasi')
                )
                ->whereIn('id_kpc', $kpcIds)
                ->when($tahun, fn($q) => $q->where('produksi.tahun_anggaran', $tahun))
                ->when($triwulan, fn($q) => $q->where('produksi.triwulan', $triwulan))
                ->groupBy('id_kpc')
                ->get()
                ->keyBy('id_kpc');

            $data = $result->map(function ($item) use ($biaya, $produksi) {
                $b = $biaya[$item->id] ?? (object)['pelaporan' => 0, 'hasil_verifikasi' => 0];
                $p = $produksi[$item->id] ?? (object)['pelaporan' => 0, 'hasil_verifikasi' => 0];

                return [
                    'id_kpc' => $item->id_kpc,
                    'nama_kpc' => $item->nama_kpc,
                    'hasil_pelaporan_biaya' => round($b->pelaporan),
                    'hasil_verifikasi_biaya' => round($b->hasil_verifikasi),
                    'hasil_pelaporan_pendapatan' => round($p->pelaporan),
                    'hasil_verifikasi_pendapatan' => round($p->hasil_verifikasi),
                    'deviasi_biaya' => round($b->pelaporan - $b->hasil_verifikasi),
                    'deviasi_produksi' => round($p->pelaporan - $p->hasil_verifikasi),
                    'deviasi_akhir' => round($b->hasil_verifikasi - $p->hasil_verifikasi),
                ];
            });

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'data' => $data,
                'total_data' => $total,
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
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Cetak Kertas Keja Verifikasi',
                'modul' => 'Kertas Keja Verifikasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            $export = new LaporanKertasKerjaVerifikasiExport($data);
            $spreadsheet = $export->getSpreadsheet();
            $writer = new Xlsx($spreadsheet);

            $filename = 'laporan-kertas-kerja-verifikasi.xlsx';
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
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
            $query = Kpc::where('kpc.id_kprk', $request->id_kprk)
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

            $export = new LaporanKertasKerjaVerifikasiDetailExport($data);
            $spreadsheet = $export->getSpreadsheet();
            $writer = new Xlsx($spreadsheet);

            $filename = 'laporan-kertas-kerja-verifikasi-detail.xlsx';
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
