<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Kpc;
use App\Models\Npp;
use App\Models\Kprk;
use App\Models\Status;
use App\Models\UserLog;
use ZipStream\ZipStream;
use App\Helpers\LtkHelper;
use App\Models\LayananKurir;
use Illuminate\Http\Request;
use App\Models\VerifikasiLtk;
use App\Models\LockVerifikasi;
use App\Models\ProduksiDetail;
use App\Models\ProduksiNasional;
use Illuminate\Support\Facades\DB;
use App\Models\LayananJasaKeuangan;
use Illuminate\Support\Facades\Log;
use App\Models\VerifikasiBiayaRutin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\VerifikasiBiayaRutinDetail;
use Symfony\Component\HttpFoundation\Response;
use App\Models\VerifikasiBiayaRutinDetailLampiran;
// use ZipStream\Option\Archive as ArchiveOptions;



class VerifikasiBiayaRutinController extends Controller
{
    protected $ltkHelper;
    public function __construct(LtkHelper $ltkHelper)
    {
        $this->ltkHelper = $ltkHelper;
    }

    public function getPerTahun(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'status' => 'nullable|string|in:7,9',
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => 'nullable|string',
                'search' => 'nullable|string'
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

            // Param
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 100);
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $tahun = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');
            $statusFilter = $request->get('status', '');

            $orderMappings = [
                'namaASC' => 'regional.nama ASC',
                'namaDESC' => 'regional.nama DESC',
                'triwulanASC' => 'verifikasi_biaya_rutin.triwulan ASC',
                'triwulanDESC' => 'verifikasi_biaya_rutin.triwulan DESC',
                'tahunASC' => 'verifikasi_biaya_rutin.tahun ASC',
                'tahunDESC' => 'verifikasi_biaya_rutin.tahun DESC',
            ];
            $order = $orderMappings[$getOrder] ?? 'regional.nama ASC';

            // Query utama
            $rutinQuery = VerifikasiBiayaRutinDetail::select(
                'verifikasi_biaya_rutin.triwulan',
                'verifikasi_biaya_rutin.tahun',
                'regional.nama as nama_regional',
                'regional.id as id_regional',
                DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_biaya')
            )
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->groupBy('verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun')
                ->orderByRaw($order);

            if ($search !== '') {
                $rutinQuery->where(function ($query) use ($search) {
                    $query->where('verifikasi_biaya_rutin.id', 'like', "%$search%")
                        ->orWhere('verifikasi_biaya_rutin.id_regional', 'like', "%$search%")
                        ->orWhere('verifikasi_biaya_rutin.triwulan', 'like', "%$search%")
                        ->orWhere('verifikasi_biaya_rutin.tahun', 'like', "%$search%")
                        ->orWhere('regional.nama', 'like', "%$search%");
                });
            }

            if ($tahun !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
            }
            if ($triwulan !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            }
            if ($statusFilter !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_status', $statusFilter);
            }

            $total_data = DB::table('verifikasi_biaya_rutin')
                ->join('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->when($tahun, fn($q) => $q->where('tahun', $tahun))
                ->when($triwulan, fn($q) => $q->where('triwulan', $triwulan))
                ->when($statusFilter, fn($q) => $q->where('id_status', $statusFilter))
                ->select(DB::raw('COUNT(DISTINCT CONCAT(verifikasi_biaya_rutin.id_regional, "-", verifikasi_biaya_rutin.triwulan, "-", verifikasi_biaya_rutin.tahun)) as total'))
                ->value('total');

            $rutin = $rutinQuery->offset($offset)->limit($limit)->get();
            $grand_total_raw = $rutin->sum('total_biaya');
            $grand_total = "Rp " . number_format($grand_total_raw, 2, ',', '.');

            $groupKeys = $rutin->map(fn($item) => $item->id_regional . '-' . $item->triwulan . '-' . $item->tahun)->unique();

            $statuses = VerifikasiBiayaRutin::where('tahun', $tahun)
                ->when($triwulan, fn($q) => $q->where('triwulan', $triwulan))
                ->whereIn(DB::raw("CONCAT(id_regional, '-', triwulan, '-', tahun)"), $groupKeys)
                ->select('id_regional', 'triwulan', 'tahun', 'id_status')
                ->get()
                ->groupBy(fn($item) => $item->id_regional . '-' . $item->triwulan . '-' . $item->tahun);

            $statusNames = Status::whereIn('id', [7, 9])->pluck('nama', 'id');

            foreach ($rutin as $item) {
                $item->total_biaya = "Rp " . number_format(round($item->total_biaya), 0, '', '.');
                $key = $item->id_regional . '-' . $item->triwulan . '-' . $item->tahun;
                $statusList = $statuses[$key] ?? collect();
                $semuaStatusSembilan = $statusList->pluck('id_status')->every(fn($id_status) => $id_status == 9);
                $status_id = $semuaStatusSembilan ? 9 : 7;
                $item->status = $statusNames[$status_id] ?? '-';
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'grand_total' => $grand_total,
                'data' => $rutin,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

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
            $tahun = $request->get('tahun', '');
            $triwulan = $request->get('triwulan', '');
            $status = $request->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'verifikasi_biaya_rutin.triwulan ASC',
                'triwulanDESC' => 'verifikasi_biaya_rutin.triwulan DESC',
                'tahunASC' => 'verifikasi_biaya_rutin.tahun ASC',
                'tahunDESC' => 'verifikasi_biaya_rutin.tahun DESC',
            ];
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            $validOrderValues = implode(',', array_keys($orderMappings));
            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ], [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select(
                    'kprk.jumlah_kpc_lpu',
                    'verifikasi_biaya_rutin.id',
                    'verifikasi_biaya_rutin.triwulan',
                    'verifikasi_biaya_rutin.tahun',
                    'regional.nama as nama_regional',
                    'regional.id as id_regional',
                    'kprk.id as id_kcu',
                    'kprk.nama as nama_kcu',
                    DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_biaya')
                )
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->groupBy('kprk.id', 'verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama');

            if ($search !== '') {
                $rutinQuery->where(function ($query) use ($search) {
                    $query->where('verifikasi_biaya_rutin.id', 'like', "%$search%")
                        ->orWhere('verifikasi_biaya_rutin.id_regional', 'like', "%$search%")
                        ->orWhere('verifikasi_biaya_rutin.triwulan', 'like', "%$search%")
                        ->orWhere('verifikasi_biaya_rutin.tahun', 'like', "%$search%")
                        ->orWhere('verifikasi_biaya_rutin.total_biaya', 'like', "%$search%")
                        ->orWhere('verifikasi_biaya_rutin.id_status', 'like', "%$search%")
                        ->orWhere('regional.nama', 'like', "%$search%")
                        ->orWhere('kprk.nama', 'like', "%$search%");
                });
            }

            if ($id_regional !== '') $rutinQuery->where('verifikasi_biaya_rutin.id_regional', $id_regional);
            if ($tahun !== '') $rutinQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
            if ($triwulan !== '') $rutinQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            if ($status !== '') $rutinQuery->where('verifikasi_biaya_rutin.id_status', $status);

            $total = $rutinQuery->count();
            $data = $rutinQuery->offset($offset)->limit($limit)->get();

            foreach ($data as $item) {
                $jumlah_lpu = $item->jumlah_kpc_lpu;
                $jumlah_pengawas = 0;
                if ($jumlah_lpu <= 10) $jumlah_pengawas = 1;
                elseif ($jumlah_lpu <= 20) $jumlah_pengawas = 2;
                elseif ($jumlah_lpu <= 30) $jumlah_pengawas = 3;
                elseif ($jumlah_lpu <= 40) $jumlah_pengawas = 4;
                else $jumlah_pengawas = 5;

                $item->total_biaya = "Rp " . number_format(round($item->total_biaya), 0, '', '.');
                $item->jumlah_pengawas = $jumlah_pengawas;

                $getBiayaRutin = VerifikasiBiayaRutin::where('tahun', $item->tahun)
                    ->where('id_regional', $item->id_regional)
                    ->where('id_kprk', $item->id_kcu)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                $semuaStatusSembilan = $getBiayaRutin->every(fn($biayaRutin) => $biayaRutin->id_status == 9);
                $statusModel = Status::find($semuaStatusSembilan ? 9 : 7);
                $item->status = $statusModel ? $statusModel->nama : null;
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total,
                'data' => $data,
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

            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $id_kcu = request()->get('id_kcu', '');
            $tahun = request()->get('tahun', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
            $orderMappings = [
                'namakpcASC' => 'kpc.nama ASC',
                'namakpcDESC' => 'kpc.nama DESC',
                'namakcuASC' => 'kprk.nama ASC',
                'namakcuDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'verifikasi_biaya_rutin.triwulan ASC',
                'triwulanDESC' => 'verifikasi_biaya_rutin.triwulan DESC',
                'tahunASC' => 'verifikasi_biaya_rutin.tahun ASC',
                'tahunDESC' => 'verifikasi_biaya_rutin.tahun DESC',
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

            // ===================== TANPA CACHE =====================

            $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select(
                    'verifikasi_biaya_rutin.id as id_verifikasi_biaya_rutin',
                    'verifikasi_biaya_rutin.triwulan',
                    'verifikasi_biaya_rutin.tahun',
                    'verifikasi_biaya_rutin.id_status',
                    'regional.nama as nama_regional',
                    'regional.id as id_regional',
                    'kprk.id as id_kcu',
                    'kprk.nama as nama_kcu',
                    'kpc.id as id_kpc',
                    'kpc.nama as nama_kpc',
                    DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_biaya')
                )
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
                ->join('kpc', 'verifikasi_biaya_rutin.id_kpc', '=', 'kpc.id')
                ->groupBy(
                    'kpc.id',
                    'kprk.id',
                    'verifikasi_biaya_rutin.id_regional',
                    'verifikasi_biaya_rutin.triwulan',
                    'verifikasi_biaya_rutin.tahun',
                    'regional.nama'
                );

            $rutinQuery->where(function ($query) use ($search) {
                $query->where('verifikasi_biaya_rutin.id', 'like', "%$search%")
                    ->orWhere('verifikasi_biaya_rutin.id_regional', 'like', "%$search%")
                    ->orWhere('verifikasi_biaya_rutin.triwulan', 'like', "%$search%")
                    ->orWhere('verifikasi_biaya_rutin.tahun', 'like', "%$search%")
                    ->orWhere('verifikasi_biaya_rutin.total_biaya', 'like', "%$search%")
                    ->orWhere('verifikasi_biaya_rutin.id_status', 'like', "%$search%")
                    ->orWhere('regional.nama', 'like', "%$search%")
                    ->orWhere('kpc.nama', 'like', "%$search%")
                    ->orWhere('kprk.nama', 'like', "%$search%");
            });

            if ($id_kcu !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_kprk', $id_kcu);
            }

            if ($tahun !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
            }

            if ($triwulan !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            }

            $total_data = $rutinQuery->count();
            $rutin = $rutinQuery->offset($offset)->limit($limit)->get();
            foreach ($rutin as $item) {
                $item->total_biaya = "Rp " . number_format(round($item->total_biaya), 0, '', '.');
                $statusData = Status::firstWhere('id', $item->id_status);
                $item->status = $statusData->nama;
            }


            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $rutin,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }




    // public function getPerKPC(Request $request)
    // {
    //     try {
    //         $offset = $request->get('offset', 0);
    //         $limit = $request->get('limit', 100);
    //         $getOrder = $request->get('order', '');
    //         $id_verifikasi_biaya_rutin = $request->get('id_verifikasi_biaya_rutin');
    //         $id_kcu = $request->get('id_kcu');
    //         $id_kpc = $request->get('id_kpc');

    //         // Validasi awal
    //         $validator = Validator::make($request->all(), [
    //             'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
    //             'id_kpc' => 'required|string|exists:kpc,id',
    //             'id_kcu' => 'required|numeric|exists:kprk,id',
    //             'offset' => 'integer|min:0',
    //             'limit' => 'integer|min:1',
    //             'order' => 'nullable|string|in:koderekeningASC,koderekeningDESC,namaASC,namaDESC',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => $validator->errors(),
    //                 'error_code' => 'INPUT_VALIDATION_ERROR',
    //             ], 422);
    //         }

    //         $orderMappings = [
    //             'koderekeningASC' => 'rekening_biaya.koderekening ASC',
    //             'koderekeningDESC' => 'rekening_biaya.koderekening DESC',
    //             'namaASC' => 'rekening_biaya.nama ASC',
    //             'namaDESC' => 'rekening_biaya.nama DESC',
    //         ];
    //         $order = $orderMappings[$getOrder] ?? 'rekening_biaya.kode_rekening ASC';

    //         // Ambil data utama
    //         $rutinQuery = VerifikasiBiayaRutinDetail::select(
    //             'rekening_biaya.kode_rekening',
    //             'rekening_biaya.nama as nama_rekening',
    //             'verifikasi_biaya_rutin.triwulan',
    //             'verifikasi_biaya_rutin.tahun',
    //             'verifikasi_biaya_rutin_detail.bulan'
    //         )
    //             ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
    //             ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
    //             ->where('verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', $id_verifikasi_biaya_rutin)
    //             ->where('verifikasi_biaya_rutin.id_kprk', $id_kcu)
    //             ->where('verifikasi_biaya_rutin.id_kpc', $id_kpc)
    //             ->orderByRaw($order)
    //             ->get();

    //         $bulanIndonesia = [
    //             'Januari',
    //             'Februari',
    //             'Maret',
    //             'April',
    //             'Mei',
    //             'Juni',
    //             'Juli',
    //             'Agustus',
    //             'September',
    //             'Oktober',
    //             'November',
    //             'Desember'
    //         ];

    //         $groupedRutin = [];

    //         foreach ($rutinQuery as $item) {
    //             $kodeRekening = $item->kode_rekening;
    //             $triwulan = $item->triwulan;
    //             $tahun = $item->tahun;

    //             if (!isset($groupedRutin[$kodeRekening])) {
    //                 $groupedRutin[$kodeRekening] = [
    //                     'kode_rekening' => $kodeRekening,
    //                     'nama_rekening' => $item->nama_rekening,
    //                     'laporan' => [],
    //                 ];
    //             }

    //             $bulanAwal = ($triwulan - 1) * 3 + 1;

    //             for ($i = $bulanAwal; $i <= $bulanAwal + 2; $i++) {
    //                 $bulanString = $bulanIndonesia[$i - 1];

    //                 // Ambil pelaporan & verifikasi
    //                 $data = VerifikasiBiayaRutinDetail::select(
    //                     DB::raw('SUM(pelaporan) as total_pelaporan'),
    //                     DB::raw('SUM(verifikasi) as total_verifikasi')
    //                 )
    //                     ->where([
    //                         ['bulan', $i],
    //                         ['id_rekening_biaya', $kodeRekening],
    //                         ['id_verifikasi_biaya_rutin', $id_verifikasi_biaya_rutin],
    //                     ])
    //                     ->first();

    //                 // Ambil lampiran
    //                 $lampiranData = VerifikasiBiayaRutinDetail::select(
    //                     'verifikasi_biaya_rutin_detail.lampiran',
    //                     'verifikasi_biaya_rutin_detail_lampiran.nama_file'
    //                 )
    //                     ->leftJoin(
    //                         'verifikasi_biaya_rutin_detail_lampiran',
    //                         'verifikasi_biaya_rutin_detail.id',
    //                         '=',
    //                         'verifikasi_biaya_rutin_detail_lampiran.verifikasi_biaya_rutin_detail'
    //                     )
    //                     ->where([
    //                         ['bulan', $i],
    //                         ['id_rekening_biaya', $kodeRekening],
    //                         ['id_verifikasi_biaya_rutin', $id_verifikasi_biaya_rutin],
    //                     ])
    //                     ->first();

    //                 $lampiran = ($lampiranData && $lampiranData->lampiran === 'Y') ? 'Y' : 'N';
    //                 $nama_file = $lampiranData->nama_file ?? null;

    //                 // Cek status lock
    //                 $isLockStatus = LockVerifikasi::where([
    //                     ['tahun', $tahun],
    //                     ['bulan', $i],
    //                 ])
    //                     ->value('status') ?? false;

    //                 $groupedRutin[$kodeRekening]['laporan'][] = [
    //                     'bulan_string' => $bulanString,
    //                     'bulan' => $i,
    //                     'pelaporan' => 'Rp. ' . number_format(round($data->total_pelaporan ?? 0), 0, '', '.'),
    //                     'verifikasi' => 'Rp. ' . number_format(round($data->total_verifikasi ?? 0), 0, '', '.'),
    //                     'isLock' => $isLockStatus,
    //                     'lampiran' => $lampiran,
    //                     'url_lampiran' => $nama_file ? config('app.env_config_path') . $nama_file : null,
    //                 ];
    //             }
    //         }

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $order,
    //             'id_kcu' => $id_kcu,
    //             'id_kpc' => $id_kpc,
    //             'total_data' => count($groupedRutin),
    //             'data' => array_values($groupedRutin),
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'ERROR',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function getPerKPC(Request $request)
    {
        try {
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 100);
            $getOrder = $request->get('order', '');
            $id_verifikasi_biaya_rutin = $request->get('id_verifikasi_biaya_rutin');
            $id_kcu = $request->get('id_kcu');
            $id_kpc = $request->get('id_kpc');

            $validator = Validator::make($request->all(), [
                'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|numeric|exists:kprk,id',
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => 'nullable|string|in:koderekeningASC,koderekeningDESC,namaASC,namaDESC',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $orderMappings = [
                'koderekeningASC' => ['kode_rekening', 'asc'],
                'koderekeningDESC' => ['kode_rekening', 'desc'],
                'namaASC' => ['nama_rekening', 'asc'],
                'namaDESC' => ['nama_rekening', 'desc'],
            ];

            $order = $orderMappings[$getOrder] ?? ['kode_rekening', 'asc'];

            $lampiranDistinct = DB::table('verifikasi_biaya_rutin_detail_lampiran')
                ->select('verifikasi_biaya_rutin_detail', 'nama_file')
                ->groupBy('verifikasi_biaya_rutin_detail', 'nama_file');

            $details = DB::table('verifikasi_biaya_rutin_detail as d')
                ->leftJoin('rekening_biaya as r', 'r.id', '=', 'd.id_rekening_biaya')
                ->join('verifikasi_biaya_rutin as v', 'v.id', '=', 'd.id_verifikasi_biaya_rutin')
                ->leftJoinSub($lampiranDistinct, 'l', function ($join) {
                    $join->on('l.verifikasi_biaya_rutin_detail', '=', 'd.id');
                })
                ->where('d.id_verifikasi_biaya_rutin', $id_verifikasi_biaya_rutin)
                ->where('v.id_kpc', $id_kpc)
                ->where('v.id_kprk', $id_kcu)
                ->select([
                    'd.*',
                    'v.triwulan',
                    'r.kode_rekening',
                    'r.nama as nama_rekening',
                    'l.nama_file as lampiran_nama_file',
                ])
                ->get();

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

            $lockStatusMap = LockVerifikasi::where('tahun', now()->year)
                ->pluck('status', 'bulan')
                ->toArray();

            $grouped = [];

            foreach ($details as $detail) {
                $kode = $detail->kode_rekening;
                $nama = $detail->nama_rekening;
                $bulan = intval($detail->bulan);
                $triwulan = $detail->triwulan;

                if (!isset($grouped[$kode])) {
                    $grouped[$kode] = [
                        'kode_rekening' => (int)$kode,
                        'nama_rekening' => $nama,
                        'laporan' => [],
                    ];
                }

                $key = array_search($bulan, range(($triwulan - 1) * 3 + 1, $triwulan * 3));
                if ($key === false) continue;

                $index = $bulan - 1;
                $lampiran = $detail->lampiran_nama_file ? 'Y' : 'N';
                $url_lampiran = $lampiran === 'Y' ? config('app.env_config_path') . $detail->lampiran_nama_file : null;

                $existingIndex = null;
                foreach ($grouped[$kode]['laporan'] as $i => $lap) {
                    if ($lap['bulan'] === $bulan) {
                        $existingIndex = $i;
                        break;
                    }
                }

                if ($existingIndex !== null) {
                    $grouped[$kode]['laporan'][$existingIndex]['pelaporan'] =
                        'Rp. ' . number_format(
                            str_replace('.', '', str_replace('Rp. ', '', $grouped[$kode]['laporan'][$existingIndex]['pelaporan']))
                                + round($detail->pelaporan),
                            0,
                            '',
                            '.'
                        );

                    $grouped[$kode]['laporan'][$existingIndex]['verifikasi'] =
                        'Rp. ' . number_format(
                            str_replace('.', '', str_replace('Rp. ', '', $grouped[$kode]['laporan'][$existingIndex]['verifikasi']))
                                + round($detail->verifikasi),
                            0,
                            '',
                            '.'
                        );

                    if ($lampiran === 'Y') {
                        $grouped[$kode]['laporan'][$existingIndex]['lampiran'] = 'Y';
                        $grouped[$kode]['laporan'][$existingIndex]['url_lampiran'] = $url_lampiran;
                    }
                } else {
                    $grouped[$kode]['laporan'][] = [
                        'bulan_string' => $bulanIndonesia[$index],
                        'bulan' => $bulan,
                        'pelaporan' => 'Rp. ' . number_format(round($detail->pelaporan), 0, '', '.'),
                        'verifikasi' => 'Rp. ' . number_format(round($detail->verifikasi), 0, '', '.'),
                        'isLock' => $lockStatusMap[$bulan] ?? false,
                        'lampiran' => $lampiran,
                        'url_lampiran' => $url_lampiran,
                    ];
                }
            }

            foreach ($grouped as &$item) {
                $existingBulan = collect($item['laporan'])->pluck('bulan')->toArray();

                if (!empty($item['laporan'])) {
                    $triwulanAwal = ceil($item['laporan'][0]['bulan'] / 3);
                    $bulanDalamTriwulan = range(($triwulanAwal - 1) * 3 + 1, $triwulanAwal * 3);

                    foreach ($bulanDalamTriwulan as $bln) {
                        if (!in_array($bln, $existingBulan)) {
                            $item['laporan'][] = [
                                'bulan_string' => $bulanIndonesia[$bln - 1],
                                'bulan' => $bln,
                                'pelaporan' => 'Rp. 0',
                                'verifikasi' => 'Rp. 0',
                                'isLock' => $lockStatusMap[$bln] ?? false,
                                'lampiran' => 'N',
                                'url_lampiran' => null,
                            ];
                        }
                    }
                }

                usort($item['laporan'], fn($a, $b) => $a['bulan'] <=> $b['bulan']);
            }

            $groupedCollection = collect($grouped)
                ->sortBy($order[0], SORT_REGULAR, $order[1] === 'desc')
                ->values();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => (int)$offset,
                'limit' => (int)$limit,
                'order' => $order[0] . ' ' . strtoupper($order[1]),
                'id_kcu' => (string)$id_kcu,
                'id_kpc' => (string)$id_kpc,
                'total_data' => $groupedCollection->count(),
                'data' => $groupedCollection,
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
            $id_verifikasi_biaya_rutin = request()->get('id_verifikasi_biaya_rutin', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $status = 10;
            $validator = Validator::make($request->all(), [
                'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
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
            $rutin = VerifikasiBiayaRutin::where('id', $request->id_verifikasi_biaya_rutin)
                ->where('id_kprk', $request->id_kcu)
                ->where('id_kpc', $request->id_kpc)->first();
            $rutin->update([
                'id_status' => 10,
            ]);

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Update Simpling Biaya Rutin',
                'modul' => 'Biaya Rutin',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $rutin]);
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
            $produksiKurir = ProduksiNasional::whereIn('produk', $this->getLayananKurir())
                ->where('status', 'OUTGOING')
                ->where('tahun', $tahun)
                ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
                ->sum('jml_produksi') ?? 0;

            $kodeRekeningJaskug = [
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

            $pendapatanJaskug = DB::table('verifikasi_ltk')
                ->whereIn('kode_rekening', $kodeRekeningJaskug)
                ->where('tahun', $tahun)
                ->where('bulan', str_pad($bulan, 2, '0', STR_PAD_LEFT))
                ->sum('mtd_akuntansi') ?? 0;

            return [
                'produksi_kurir' => $produksiKurir,
                'pendapatan_jaskug' => $pendapatanJaskug,
                'total_pendapatan' => $produksiKurir + $pendapatanJaskug,
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

            $produksiJaskugPerKCPLpu = $totalKcpLPU > 0 ? ($produksiJaskugKCPLpuNasional / $totalKcpLPU) : 0;

            switch (strtoupper($kategoriCost)) {
                case 'FULLCOST':
                case 'FULL':
                case '100%':
                    $proporsiBiayaJaskugNasional = $biayaPso * 1.0;

                    $rumusFase2 = $produksiJaskugNasional > 0 ?
                        ($produksiJaskugKCPLpuNasional / $produksiJaskugNasional) : 0;
                    $proporsiBiayaJaskugKCPLpu = $proporsiBiayaJaskugNasional * $rumusFase2;

                    $rumusFase3 = $produksiJaskugKCPLpuNasional > 0 ?
                        ($produksiJaskugPerKCPLpu / $produksiJaskugKCPLpuNasional) : 0;
                    $proporsiBiayaPerKCPLpu = $proporsiBiayaJaskugKCPLpu * $rumusFase3;

                    $proporsiData = [
                        'rumus_fase_1' => 'Biaya * 100%',
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiayaJaskugNasional, 0, ',', '.'),
                        'rumus_fase_2' => 'Jumlah Proporsi Biaya Jaskug Nasional * Produksi Jaskug KCP LPU Nasional / Produksi Jaskug Nasional',
                        'hasil_perhitungan_fase_2' => number_format($proporsiBiayaJaskugKCPLpu, 0, ',', '.'),
                        'rumus_fase_3' => 'Proporsi Biaya Jaskug KCP LPU Nasional * Produksi Jaskug per KCP LPU / Produksi Jaskug KCP LPU Nasional',
                        'hasil_perhitungan_fase_3' => number_format($proporsiBiayaPerKCPLpu, 0, ',', '.'),
                        'fase_1_value' => $proporsiBiayaJaskugNasional,
                        'fase_2_value' => $proporsiBiayaJaskugKCPLpu,
                        'fase_3_value' => $proporsiBiayaPerKCPLpu,
                        'produksi_jaskug_nasional' => $produksiJaskugNasional,
                        'produksi_jaskug_kcp_lpu_nasional' => $produksiJaskugKCPLpuNasional,
                        'total_kcp_lpu' => $totalKcpLPU,
                        'produksi_jaskug_per_kcp_lpu' => $produksiJaskugPerKCPLpu,
                        'tahun' => $tahun,
                        'bulan' => $bulan
                    ];
                    break;

                case 'JOINTCOST':
                case 'JOIN':
                case 'JOIN COST':
                    $joinCost = $this->calculateJoinCost('', $tahun, $bulan);
                    $produksiJaskug = $joinCost['produksi_jaskug'] ?? 0;
                    $produksiKurir = $joinCost['produksi_kurir'] ?? 0;
                    $totalProduksi = $produksiJaskug + $produksiKurir;

                    $rumusFase1 = $totalProduksi > 0 ? ($produksiJaskug / $totalProduksi) : 0;
                    $proporsiBiayaJaskugNasional = $biayaPso * $rumusFase1;

                    $rumusFase2 = $produksiJaskugNasional > 0 ?
                        ($produksiJaskugKCPLpuNasional / $produksiJaskugNasional) : 0;
                    $proporsiBiayaJaskugKCPLpu = $proporsiBiayaJaskugNasional * $rumusFase2;

                    $rumusFase3 = $produksiJaskugKCPLpuNasional > 0 ?
                        ($produksiJaskugPerKCPLpu / $produksiJaskugKCPLpuNasional) : 0;
                    $proporsiBiayaPerKCPLpu = $proporsiBiayaJaskugKCPLpu * $rumusFase3;

                    $proporsiData = [
                        'rumus_fase_1' => 'Biaya * Produksi Produk Jaskug / Produksi Produk Jaskug + Produksi Produk Kurir',
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiayaJaskugNasional, 0, ',', '.'),
                        'rumus_fase_2' => 'Proporsi Biaya Jaskug Nasional * Produksi Jaskug KCP LPU Nasional / Produksi Jaskug Nasional',
                        'hasil_perhitungan_fase_2' => number_format($proporsiBiayaJaskugKCPLpu, 0, ',', '.'),
                        'rumus_fase_3' => 'Proporsi Biaya Jaskug KCP LPU Nasional * Produksi Jaskug per KCP LPU / Produksi Jaskug KCP LPU Nasional',
                        'hasil_perhitungan_fase_3' => number_format($proporsiBiayaPerKCPLpu, 0, ',', '.'),
                        'fase_1_value' => $proporsiBiayaJaskugNasional,
                        'fase_2_value' => $proporsiBiayaJaskugKCPLpu,
                        'fase_3_value' => $proporsiBiayaPerKCPLpu,
                        'detail_jaskug' => $joinCost['detail_jaskug'] ?? [],
                        'produksi_jaskug' => $produksiJaskug,
                        'produksi_kurir' => $produksiKurir,
                        'total_produksi' => $totalProduksi,
                        'rumus_fase_1_ratio' => $rumusFase1,
                        'produksi_jaskug_nasional' => $produksiJaskugNasional,
                        'produksi_jaskug_kcp_lpu_nasional' => $produksiJaskugKCPLpuNasional,
                        'total_kcp_lpu' => $totalKcpLPU,
                        'produksi_jaskug_per_kcp_lpu' => $produksiJaskugPerKCPLpu,
                        'tahun' => $tahun,
                        'bulan' => $bulan
                    ];
                    break;

                case 'COMMONCOST':
                case 'COMMON':
                case 'COMMON COST':
                    $commonCost = $this->calculateCommonCost('', $tahun, $bulan);
                    $pendapatanJaskug = $commonCost['pendapatan_jaskug'] ?? 0;
                    $pendapatanKurir = $commonCost['produksi_kurir'] ?? 0;
                    $totalPendapatan = $pendapatanJaskug + $pendapatanKurir;

                    $rumusFase1 = $totalPendapatan > 0 ? ($pendapatanJaskug / $totalPendapatan) : 0;
                    $proporsiBiayaJaskugNasional = $biayaPso * $rumusFase1;

                    $rumusFase2 = $produksiJaskugNasional > 0 ?
                        ($produksiJaskugKCPLpuNasional / $produksiJaskugNasional) : 0;
                    $proporsiBiayaJaskugKCPLpu = $proporsiBiayaJaskugNasional * $rumusFase2;

                    $rumusFase3 = $produksiJaskugKCPLpuNasional > 0 ?
                        ($produksiJaskugPerKCPLpu / $produksiJaskugKCPLpuNasional) : 0;
                    $proporsiBiayaPerKCPLpu = $proporsiBiayaJaskugKCPLpu * $rumusFase3;

                    $proporsiData = [
                        'rumus_fase_1' => 'Biaya * Pendapatan Produk Jaskug / Pendapatan Produk Jaskug + Pendapatan Produk Kurir',
                        'hasil_perhitungan_fase_1' => number_format($proporsiBiayaJaskugNasional, 0, ',', '.'),
                        'rumus_fase_2' => 'Proporsi Biaya Jaskug Nasional * Produksi Jaskug KCP LPU Nasional / Produksi Jaskug Nasional',
                        'hasil_perhitungan_fase_2' => number_format($proporsiBiayaJaskugKCPLpu, 0, ',', '.'),
                        'rumus_fase_3' => 'Proporsi Biaya Jaskug KCP LPU Nasional * Produksi Jaskug per KCP LPU / Produksi Jaskug KCP LPU Nasional',
                        'hasil_perhitungan_fase_3' => number_format($proporsiBiayaPerKCPLpu, 0, ',', '.'),
                        'fase_1_value' => $proporsiBiayaJaskugNasional,
                        'fase_2_value' => $proporsiBiayaJaskugKCPLpu,
                        'fase_3_value' => $proporsiBiayaPerKCPLpu,
                        'pendapatan_jaskug' => $pendapatanJaskug,
                        'pendapatan_kurir' => $pendapatanKurir,
                        'total_pendapatan' => $totalPendapatan,
                        'rumus_fase_1_ratio' => $rumusFase1,
                        'produksi_jaskug_nasional' => $produksiJaskugNasional,
                        'produksi_jaskug_kcp_lpu_nasional' => $produksiJaskugKCPLpuNasional,
                        'total_kcp_lpu' => $totalKcpLPU,
                        'produksi_jaskug_per_kcp_lpu' => $produksiJaskugPerKCPLpu,
                        'tahun' => $tahun,
                        'bulan' => $bulan
                    ];
                    break;

                default:
                    throw new \Exception("Invalid kategori_cost: {$kategoriCost}");
            }
        } catch (\Exception $e) {
            Log::error("Error in calculateProporsiByCategory: " . $e->getMessage(), [
                'kategori_cost' => $kategoriCost,
                'biaya_pso' => $biayaPso,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'trace' => $e->getTraceAsString()
            ]);
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
            $id_verifikasi_biaya_rutin = request()->get('id_verifikasi_biaya_rutin', '');
            $kode_rekening = request()->get('kode_rekening', '');
            $bulan = str_pad(request()->get('bulan', ''), 2, '0', STR_PAD_LEFT);
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');

            $validator = Validator::make($request->all(), [
                'bulan' => 'required|numeric|max:12',
                'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|string|exists:kprk,id',
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

            $lampiranDistinct = DB::table('verifikasi_biaya_rutin_detail_lampiran')
                ->select('verifikasi_biaya_rutin_detail', 'nama_file')
                ->groupBy('verifikasi_biaya_rutin_detail', 'nama_file');

            $rutin = VerifikasiBiayaRutinDetail::select(
                'kpc.nama as nama_kcp',
                'verifikasi_biaya_rutin_detail.kategori_biaya',
                'verifikasi_biaya_rutin_detail.id as id_verifikasi_biaya_rutin_detail',
                'rekening_biaya.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'verifikasi_biaya_rutin.tahun',
                DB::raw("CONCAT('" . $bulanIndonesia[$bulan - 1] . "') AS periode"),
                'verifikasi_biaya_rutin_detail.keterangan',
                'verifikasi_biaya_rutin_detail.lampiran',
                'verifikasi_biaya_rutin_detail.pelaporan',
                'verifikasi_biaya_rutin_detail.verifikasi',
                'verifikasi_biaya_rutin_detail.catatan_pemeriksa',
                'lamp.nama_file'
            )
            ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
            ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
            ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
            ->join('kpc', 'verifikasi_biaya_rutin.id_kpc', '=', 'kpc.id')
            ->leftJoinSub($lampiranDistinct, 'lamp', function ($join) {
                $join->on('verifikasi_biaya_rutin_detail.id', '=', 'lamp.verifikasi_biaya_rutin_detail');
            })
            ->where('verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', $id_verifikasi_biaya_rutin)
            ->where('verifikasi_biaya_rutin_detail.id_rekening_biaya', $kode_rekening)
            ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
            ->where('verifikasi_biaya_rutin.id_kprk', $id_kcu)
            ->where('verifikasi_biaya_rutin.id_kpc', $id_kpc)
            ->get();

            $isLockStatus = false;

            $firstItem = $rutin->first();
            $lockVerifikasi = null;
            $npp = null;
            $produksi = 0;
            $produksi_nasional = 0;
            $kpcTotal = 2460;
            $tahun = null;
            $kodeRekening = null;

            if ($firstItem) {
                $tahun = $firstItem->tahun;
                $kodeRekening = $firstItem->kode_rekening;

                $lockVerifikasi = LockVerifikasi::where('tahun', $tahun)->where('bulan', $bulan)->first();
                $isLockStatus = $lockVerifikasi?->status ?? false;

                $npp = Npp::where('id_rekening_biaya', $kodeRekening)
                    ->where('tahun', $tahun)
                    ->where('bulan', $bulan)
                    ->first();

                $lastTwoDigits = substr($kodeRekening, -2);
                $produksiRek = ["06", "07", "08", "09"];
                $pendapatanRek = ["10", "11"];
                $sumField = null;
                $sumFieldKCP = null;
                $dataType = null;

                if (in_array($lastTwoDigits, $pendapatanRek)) {
                    $sumField = 'jml_pendapatan';
                    $sumFieldKCP = 'bsu_bruto';
                    $dataType = 'pendapatan';
                } elseif (in_array($lastTwoDigits, $produksiRek)) {
                    $sumField = 'jml_produksi';
                    $sumFieldKCP = 'bilangan';
                    $dataType = 'produksi';
                }

                $produksi_nasional = 0;
                $produksi = 0;
                if ($sumField && $dataType) {
                    $produksi_nasional = ProduksiNasional::where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->where('status', 'OUTGOING')
                        ->sum($sumField);

                    $produksi = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                        ->where('produksi.tahun_anggaran', $tahun)
                        ->where('produksi_detail.nama_bulan', $bulan)
                        ->where('jenis_produksi', 'PENERIMAAN/OUTGOING')
                        ->sum('produksi_detail.' . $sumFieldKCP);
                }
            }

            foreach ($rutin as $item) {
                $item->pelaporan = "Rp " . number_format(round($item->pelaporan), 0, '', '.');
                $item->verifikasi = "Rp " . number_format(round($item->verifikasi), 0, '', '.');

                // if ($item->kode_rekening == '5000000010') {
                //     $ltk = VerifikasiLtk::select(
                //         'verifikasi_ltk.id',
                //         'verifikasi_ltk.kode_rekening',
                //         'rekening_biaya.nama as nama_rekening',
                //         'verifikasi_ltk.bulan',
                //         'verifikasi_ltk.tahun',
                //         'verifikasi_ltk.mtd_akuntansi',
                //         'verifikasi_ltk.verifikasi_akuntansi',
                //         'verifikasi_ltk.biaya_pso',
                //         'verifikasi_ltk.verifikasi_pso',
                //         'verifikasi_ltk.mtd_biaya_pos',
                //         'verifikasi_ltk.mtd_biaya_hasil',
                //         'verifikasi_ltk.proporsi_rumus',
                //         'verifikasi_ltk.verifikasi_proporsi',
                //         'verifikasi_ltk.keterangan',
                //         'verifikasi_ltk.catatan_pemeriksa',
                //         'verifikasi_ltk.nama_file',
                //         'verifikasi_ltk.kategori_cost',
                //     )->join('rekening_biaya', 'verifikasi_ltk.kode_rekening', '=', 'rekening_biaya.id')
                //         ->where('verifikasi_ltk.bulan', $bulan)
                //         ->where('verifikasi_ltk.tahun', $tahun)
                //         ->get();

                //     // Check if collection is empty instead of null
                //     if ($ltk->isEmpty()) {
                //         continue; // Use continue instead of break to skip this iteration
                //     }

                //     $ltkSums = [
                //         'mtd_akuntansi' => $ltk->sum('mtd_akuntansi'),
                //         'verifikasi_akuntansi' => $ltk->sum('verifikasi_akuntansi'),
                //         'biaya_pso' => $ltk->sum('biaya_pso'),
                //         'verifikasi_pso' => $ltk->sum('verifikasi_pso'),
                //         'mtd_biaya_pos' => $ltk->sum('mtd_biaya_pos'),
                //         'mtd_biaya_hasil' => $ltk->sum('mtd_biaya_hasil'),
                //         'proporsi_rumus' => $ltk->sum('proporsi_rumus'),
                //         'verifikasi_proporsi' => $ltk->sum('verifikasi_proporsi'),
                //     ];

                //     $item->sums = $ltkSums;

                //     $kategoriCost = 'FULL';
                //     $mtdBiayaLtk = $ltkSums['mtd_akuntansi'];
                //     $biayaPso = $ltkSums['biaya_pso'];

                //     $proporsiCalculation = $this->calculateProporsiByCategory(
                //         $mtdBiayaLtk,
                //         $kategoriCost,
                //         $biayaPso,
                //         $tahun,
                //         $bulan
                //     );

                //     foreach ($proporsiCalculation as $key => $value) {
                //         $item->$key = $value;
                //     }
                // }

                $item->url_lampiran = config('app.env_config_path') . $item->nama_file;

                $proporsi = 0;
                if (($produksi ?? 0) != 0 && ($produksi_nasional ?? 0) != 0 && ($npp->bsu ?? 0) != 0) {
                    $persentaseProporsiProduksi = ($produksi / $produksi_nasional) * 100;
                    $roundProporsi = round($persentaseProporsiProduksi, 2);
                    $npp_verifikasi = $npp->verifikasi ?? $npp->bsu ?? 0;
                    $proporsi = $npp_verifikasi * $roundProporsi / 100;
                }

                $npp_verifikasi = $npp->verifikasi ?? $npp->bsu ?? 0;
                $item->npp = "Rp " . number_format(($npp_verifikasi ?? 0), 0, '', '.');
                $item->proporsi = "Rp " . number_format(($proporsi), 0, '', '.');

                // Prevent division by zero
                $biayaPerNpp = $kpcTotal > 0 ? ($proporsi / $kpcTotal) : 0;
                $item->biaya_per_npp = "Rp " . number_format($biayaPerNpp, 0, '', '.');
            }

            if ($isLockStatus) {
                $rutin = [];
            }
            $verifikasiLtkQuery = VerifikasiLtk::select('verifikasi_ltk.id', 'verifikasi_ltk.keterangan', 'verifikasi_ltk.id_status',  'rekening_biaya.nama as nama_rekening', 'verifikasi_ltk.kode_rekening', 'verifikasi_ltk.mtd_akuntansi', 'verifikasi_ltk.verifikasi_akuntansi', 'verifikasi_ltk.biaya_pso',  'verifikasi_ltk.verifikasi_pso', 'verifikasi_ltk.mtd_biaya_pos as mtd_biaya', 'verifikasi_ltk.mtd_biaya_hasil', 'verifikasi_ltk.proporsi_rumus', 'verifikasi_ltk.verifikasi_proporsi', 'tahun', 'bulan')
                ->join('rekening_biaya', 'verifikasi_ltk.kode_rekening', '=', 'rekening_biaya.id')->whereNot('kategori_cost', 'PENDAPATAN');

            if ($tahun !== '') {
                $verifikasiLtkQuery->where('verifikasi_ltk.tahun', $tahun);
            }

            if ($bulan !== '') {
                $verifikasiLtkQuery->where('verifikasi_ltk.bulan', $bulan);
            }

            $verifikasiLtk = $verifikasiLtkQuery->get();
            $verifikasiLtk = $verifikasiLtk->map(function ($verifikasiLtk) {
                $verifikasiLtk->nominal = (int) $verifikasiLtk->nominal;
                $verifikasiLtk->proporsi_rumus = (float) $verifikasiLtk->proporsi_rumus ?? "0.00";
                $verifikasiLtk->verifikasi_pso = (float) $verifikasiLtk->verifikasi_pso ?? "0.00";
                $verifikasiLtk->verifikasi_akuntansi = (float) $verifikasiLtk->verifikasi_akuntansi ?? "0.00";
                $verifikasiLtk->verifikasi_proporsi = (float) $verifikasiLtk->verifikasi_proporsi ?? "0.00";
                $verifikasiLtk->mtd_biaya = (float) $verifikasiLtk->mtd_biaya ?? "0.00";
                $verifikasiLtk->proporsi_rumus = $verifikasiLtk->keterangan;
                $verifikasiLtk->tahun = $verifikasiLtk->tahun ?? '';
                $verifikasiLtk->bulan = $verifikasiLtk->bulan ?? '';
                return $verifikasiLtk;
            });
            // return $verifikasiLtk;
            $grand_total_fase_1 = 0;
            foreach ($verifikasiLtk as $item) {
                $kategoriCost = $item->keterangan;
                $mtdBiayaLtk = $item->mtd_akuntansi;
                $biayaPso = $item->verifikasi_pso ?? 0;
                $fase1 = $this->ltkHelper->calculateProporsiByCategory(
                    $mtdBiayaLtk,
                    $kategoriCost,
                    $biayaPso,
                    $item->tahun,
                    $item->bulan
                );
                // Ambil hasil_perhitungan_fase_1, hilangkan format ribuan
                $hasilFase1 = isset($fase1['hasil_perhitungan_fase_1']) ? str_replace(['.', ','], ['', ''], $fase1['hasil_perhitungan_fase_1']) : 0;
                $grand_total_fase_1 += (float) $hasilFase1;
            }
            $hasilFase1PerBulan = "Rp " . number_format(round($grand_total_fase_1), 0, '', '.');

            $perhitunganFase2 = $this->ltkHelper->calculateFase2($grand_total_fase_1, $tahun, $bulan);
            $perhitunganFase3 = $this->ltkHelper->calculateFase3($perhitunganFase2['hasil_fase_2'], $tahun, $bulan, $id_kpc);
            $hasilFase2 = "Rp " . number_format(round($perhitunganFase2['hasil_fase_2']), 0, '', '.');
            $hasilFase3 = "Rp " . number_format(round($perhitunganFase3['hasil_fase_3']), 0, '', '.');
            $produksi_nasional = $perhitunganFase2['produksi_jaskug_nasional'] ?? 0;
            $total_produksi_ltk_kantor_lpu_prod_materai_dibagi_10 = $perhitunganFase2['total_produksi_ltk_kantor_lpu_prod_materai_dibagi_10'] ?? 0;
            $produksi_kcp_lpu_a = $perhitunganFase3['produksi_kcp_lpu_a'] ?? 0;
            $total_produksi_ltk_kantor_lpu = $perhitunganFase3['total_produksi_ltk_kantor_lpu'] ?? 0;

            // Masukkan hasil perhitungan fase ke dalam setiap item rutin
            foreach ($rutin as $item) {
                $item->hasil_perhitungan_fase_1 = $hasilFase1PerBulan;
                $item->rumus_fase_2 = '(Total Produksi LTK Kantor LPU(prod meterai dibagi 10) / Total Produksi Jaskug Nasional(prod meterai dibagi 10)) x Hasil Perhitungan Fase 1';
                $item->total_produksi_ltk_kantor_lpu_prod_materai_dibagi_10 = $total_produksi_ltk_kantor_lpu_prod_materai_dibagi_10;
                $item->produksi_jaskug_nasional = $produksi_nasional;
                $item->hasil_perhitungan_fase_2 = $hasilFase2;
                $item->rumus_fase_3 = '(Produksi KCP LPU A / Total Produksi LTK Kantor LPU) x Hasil Perhitungan Fase 2';
                $item->produksi_kcp_lpu_a = $produksi_kcp_lpu_a;
                $item->total_produksi_ltk_kantor_lpu = $total_produksi_ltk_kantor_lpu;
                $item->hasil_perhitungan_fase_3 = $hasilFase3;
            }

            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'link' => config('app.env_config_path'),
                'data' => $rutin,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    // public function getDetail(Request $request)
    // {
    //     try {
    //         // Ambil parameter dari request
    //         $id_verifikasi_biaya_rutin = $request->get('id_verifikasi_biaya_rutin', '');
    //         $kode_rekening = $request->get('kode_rekening', '');
    //         $bulan = str_pad($request->get('bulan', ''), 2, '0', STR_PAD_LEFT);
    //         $id_kcu = $request->get('id_kcu', '');
    //         $id_kpc = $request->get('id_kpc', '');

    //         // Validasi input
    //         $validator = Validator::make($request->all(), [
    //             'bulan' => 'required|numeric|max:12',
    //             'kode_rekening' => 'required|numeric|exists:rekening_biaya,id',
    //             'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
    //             'id_kpc' => 'required|string|exists:kpc,id',
    //             'id_kcu' => 'required|string|exists:kprk,id',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'ERROR',
    //                 'message' => 'Invalid input parameters',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         // Array bulan dalam Bahasa Indonesia
    //         $bulanIndonesia = [
    //             'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    //             'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    //         ];

    //         // Query data rutin dengan Eager Loading untuk mengurangi query tambahan
    //         $rutin = VerifikasiBiayaRutinDetail::select(
    //             'kpc.nama as nama_kcp',
    //             'verifikasi_biaya_rutin_detail.kategori_biaya',
    //             'verifikasi_biaya_rutin_detail.id as id_verifikasi_biaya_rutin_detail',
    //             'rekening_biaya.kode_rekening',
    //             'rekening_biaya.nama as nama_rekening',
    //             'verifikasi_biaya_rutin.tahun',
    //             DB::raw("'" . $bulanIndonesia[$bulan - 1] . "' AS periode"),
    //             'verifikasi_biaya_rutin_detail.keterangan',
    //             'verifikasi_biaya_rutin_detail.lampiran',
    //             'verifikasi_biaya_rutin_detail.pelaporan',
    //             'verifikasi_biaya_rutin_detail.verifikasi',
    //             'verifikasi_biaya_rutin_detail.catatan_pemeriksa',
    //             'verifikasi_biaya_rutin_detail_lampiran.nama_file'
    //         )
    //         ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
    //         ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
    //         ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
    //         ->join('kpc', 'verifikasi_biaya_rutin.id_kpc', '=', 'kpc.id')
    //         ->leftJoin('verifikasi_biaya_rutin_detail_lampiran', 'verifikasi_biaya_rutin_detail.id', '=', 'verifikasi_biaya_rutin_detail_lampiran.verifikasi_biaya_rutin_detail')
    //         ->where('verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', $id_verifikasi_biaya_rutin)
    //         ->where('verifikasi_biaya_rutin_detail.id_rekening_biaya', $kode_rekening)
    //         ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
    //         ->where('verifikasi_biaya_rutin.id_kprk', $id_kcu)
    //         ->where('verifikasi_biaya_rutin.id_kpc', $id_kpc)
    //         ->get();

    //         if ($rutin->isEmpty()) {
    //             return response()->json([
    //                 'status' => 'SUCCESS',
    //                 'data' => [],
    //             ]);
    //         }

    //         $tahun = $rutin->first()->tahun;

    //         // Check lock status and retrieve produksi_nasional in parallel
    //         $isLockStatus = LockVerifikasi::where('tahun', $tahun)
    //             ->where('bulan', $bulan)
    //             ->value('status') ?? false;

    //         $produksi_nasional = ProduksiNasional::where([
    //             ['tahun', $tahun],
    //             ['bulan', $bulan],
    //             ['status', 'OUTGOING'],
    //         ])->sum('jml_pendapatan');

    //         // Optimasi looping: ambil data NPP sekaligus untuk semua kode rekening
    //         $nppMap = Npp::whereIn('id_rekening_biaya', $rutin->pluck('kode_rekening')->unique())
    //             ->where('tahun', $tahun)
    //             ->where('bulan', $bulan)
    //             ->get()
    //             ->keyBy('id_rekening_biaya');

    //         // Looping untuk setiap item
    //         foreach ($rutin as $item) {
    //             $item->pelaporan = "Rp " . number_format(round($item->pelaporan), 0, '', '.');
    //             $item->verifikasi = "Rp " . number_format(round($item->verifikasi), 0, '', '.');
    //             $item->url_lampiran = config('app.env_config_path') . $item->nama_file;

    //             $npp = $nppMap->get($item->kode_rekening);
    //             $produksi = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //                 ->where([
    //                     ['produksi.tahun_anggaran', $item->tahun],
    //                     ['produksi_detail.nama_bulan', $bulan],
    //                     ['jenis_produksi', 'PENERIMAAN/OUTGOING'],
    //                 ])
    //                 ->sum('produksi_detail.pelaporan');

    //             $proporsi = ($produksi > 0 && $produksi_nasional > 0 && ($npp->bsu ?? 0) > 0)
    //                 ? ($produksi / $produksi_nasional) * $npp->bsu
    //                 : 0;

    //             $item->npp = "Rp " . number_format(($npp->bsu ?? 0), 0, '', '.');
    //             $item->proporsi = "Rp " . number_format($proporsi, 0, '', '.');
    //         }

    //         if ($isLockStatus) {
    //             $rutin = [];
    //         }

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'isLock' => $isLockStatus,
    //             'link' => config('app.env_config_path'),
    //             'data' => $rutin,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'ERROR',
    //             'message' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ], 500);
    //     }
    // }


    public function downloadLampiran(Request $request)
    {
        try {
            $id_verifikasi_biaya_rutin = $request->get('id_verifikasi_biaya_rutin', '');
            $kode_rekening = $request->get('kode_rekening', '');
            $bulan = str_pad($request->get('bulan', ''), 2, '0', STR_PAD_LEFT);
            $id_kcu = $request->get('id_kcu', '');
            $id_kpc = $request->get('id_kpc', '');

            $validator = Validator::make($request->all(), [
                'bulan' => 'required|numeric|max:12',
                'kode_rekening' => 'required|numeric|exists:rekening_biaya,id',
                'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|string|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $rutin = VerifikasiBiayaRutinDetailLampiran::select('verifikasi_biaya_rutin_detail_lampiran.*')
                ->join('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin_detail_lampiran.verifikasi_biaya_rutin_detail', '=', 'verifikasi_biaya_rutin_detail.id')
                ->where('verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', $id_verifikasi_biaya_rutin)
                ->where('verifikasi_biaya_rutin_detail.id_rekening_biaya', $kode_rekening)
                ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
                ->get();

            $dateNow = date('Ymd');
            $zipFileName = "{$id_kpc}-{$kode_rekening}-{$bulan}-{$dateNow}.zip";

            // Create a new ZipArchive instance
            $zip = new ZipArchive();
            $zipContent = '';
            $tempFile = tempnam(sys_get_temp_dir(), 'zip');

            // Open the ZIP file in memory
            $zip->open($tempFile, ZipArchive::CREATE);

            // Create files to the ZIP
            foreach ($rutin as $item) {
                $filePath = config('app.env_config_path') . $item->nama_file;
                if (file_exists($filePath)) {
                    $fileContent = file_get_contents($filePath);
                    $zip->addFromString(basename($filePath), $fileContent);
                } else {
                    // Optionally log or handle missing files
                }
            }

            $zip->close();

            // Output the ZIP file directly
            return response()->stream(function () use ($tempFile) {
                readfile($tempFile);
                unlink($tempFile); // Remove the temp file after streaming
            }, 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . basename($tempFile) . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function verifikasi(Request $request)
    {
        try {
            // Validasi input dari request
            $validator = Validator::make($request->all(), [
                'data.*.id_verifikasi_biaya_rutin_detail' => 'required|string|exists:verifikasi_biaya_rutin_detail,id',
                'data.*.verifikasi' => 'required|string',
                'data.*.catatan_pemeriksa' => 'nullable|string',
            ]);

            // Cek jika validasi gagal
            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $verifikasiData = $request->input('data');
            $updatedData = [];

            // Iterasi melalui data yang diverifikasi
            foreach ($verifikasiData as $data) {
                // Cek struktur data yang benar
                if (!isset($data['id_verifikasi_biaya_rutin_detail']) || !isset($data['verifikasi'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], 400);
                }

                // Proses nilai verifikasi
                $id_verifikasi_biaya_rutin_detail = $data['id_verifikasi_biaya_rutin_detail'];
                $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
                $verifikasiFloat = round((float) $verifikasi);  // Membulatkan nilai float
                $verifikasiFormatted = (string) $verifikasiFloat; // Hilangkan pemisah ribuan (titik)
                $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                // Temukan entri VerifikasiBiayaRutinDetail
                $biaya_rutin_detail = VerifikasiBiayaRutinDetail::find($id_verifikasi_biaya_rutin_detail);

                // Cek apakah entri ditemukan
                if (!$biaya_rutin_detail) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya rutin tidak ditemukan'], 404);
                }

                // Update entri
                $biaya_rutin_detail->update([
                    'verifikasi' => $verifikasiFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                ]);

                // Tambahkan entri yang diperbarui ke array hasil
                $updatedData[] = $biaya_rutin_detail;
            }

            // Kembalikan respon sukses
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Verifikasi Biaya Rutin',
                'modul' => 'Biaya Rutin',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            // Kembalikan respon error
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function submit(Request $request)
    {
        try {
            $rutin = VerifikasiBiayaRutin::where('id', $request->data)->first();

            $rutin->update([
                'id_status' => 9,
            ]);

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Update Verifikasi Biaya Rutin',
                'modul' => 'Biaya Rutin',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            Artisan::call('cache:clear');

            return response()->json(['status' => 'SUCCESS', 'data' => $rutin]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
