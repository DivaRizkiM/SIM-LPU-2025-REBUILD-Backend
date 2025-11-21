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

            if ($search !== '') {
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
            }

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
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
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
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
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

    public function getDetail(Request $request)
    {
        try {
            $id_verifikasi_biaya_rutin = request()->get('id_verifikasi_biaya_rutin', '');
            $kode_rekening = request()->get('kode_rekening', '');
            $bulan = str_pad(request()->get('bulan', ''), 2, '0', STR_PAD_LEFT);
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $jenis_biaya = request()->get('jenis_biaya', 'RUTIN'); // ✅ Default RUTIN

            $validator = Validator::make($request->all(), [
                'bulan' => 'required|numeric|max:12',
                'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|string|exists:kprk,id',
                'jenis_biaya' => 'nullable|string|in:LTK,NPP,RUTIN', // ✅ Validasi jenis_biaya
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $bulanIndonesia = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
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
            $tahun = null;
            $kodeRekening = null;

            if ($firstItem) {
                $tahun = $firstItem->tahun;
                $kodeRekening = $firstItem->kode_rekening;

                $lockVerifikasi = LockVerifikasi::where('tahun', $tahun)->where('bulan', $bulan)->first();
                $isLockStatus = $lockVerifikasi?->status ?? false;
            }

            // ✅ OPTIMISASI: Hanya jalankan query sesuai jenis_biaya
            if ($jenis_biaya === 'LTK') {
                // ========== LOGIC KHUSUS LTK ==========
                $this->processLTK($rutin, $tahun, $bulan, $id_kpc);
            } elseif ($jenis_biaya === 'NPP') {
                // ========== LOGIC KHUSUS NPP ==========
                $this->processNPP($rutin, $tahun, $bulan, $kodeRekening);
            } else {
                // ========== LOGIC KHUSUS RUTIN (Default) ==========
                $this->processRutin($rutin);
            }

            // Format data umum
            foreach ($rutin as $item) {
                $item->pelaporan = "Rp " . number_format(round($item->pelaporan), 0, '', '.');
                $item->verifikasi = "Rp " . number_format(round($item->verifikasi), 0, '', '.');
                $item->url_lampiran = config('app.env_config_path') . $item->nama_file;
            }

            if ($isLockStatus) {
                $rutin = [];
            }

            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'link' => config('app.env_config_path'),
                'jenis_biaya' => $jenis_biaya,
                'data' => $rutin,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    // ✅ PRIVATE METHOD: Process LTK
    private function processLTK(&$rutin, $tahun, $bulan, $id_kpc)
    {
        $verifikasiLtkQuery = VerifikasiLtk::select(
            'verifikasi_ltk.id',
            'verifikasi_ltk.keterangan',
            'verifikasi_ltk.id_status',
            'verifikasi_ltk.nama_rekening as nama_rekening',
            'verifikasi_ltk.kode_rekening',
            'verifikasi_ltk.mtd_akuntansi',
            'verifikasi_ltk.verifikasi_akuntansi',
            'verifikasi_ltk.biaya_pso',
            'verifikasi_ltk.verifikasi_pso',
            'verifikasi_ltk.mtd_biaya_pos as mtd_ltk_pelaporan',
            'verifikasi_ltk.mtd_biaya_hasil as mtd_ltk_verifikasi',
            'verifikasi_ltk.proporsi_rumus',
            'verifikasi_ltk.verifikasi_proporsi',
            'tahun',
            'bulan'
        )
        ->whereNot('kategori_cost', 'PENDAPATAN')
        ->where('verifikasi_ltk.tahun', $tahun)
        ->where('verifikasi_ltk.bulan', $bulan)
        ->get();

        $grand_total_fase_1 = 0;
        foreach ($verifikasiLtkQuery as $item) {
            $kategoriCost = $item->keterangan;
            $mtdLTKVerifikasi = $item->mtd_ltk_verifikasi;
            $fase1 = $this->ltkHelper->calculateProporsiByCategory(
                $mtdLTKVerifikasi,
                $kategoriCost,
                $item->tahun,
                $item->bulan
            );
            $hasilFase1 = isset($fase1['hasil_perhitungan_fase_1_raw']) ? $fase1['hasil_perhitungan_fase_1_raw'] : 0;
            $grand_total_fase_1 += (float) $hasilFase1;
        }

        $hasilFase1PerBulan = "Rp " . number_format(round($grand_total_fase_1), 0, '', '.');
        $perhitunganFase2 = $this->ltkHelper->calculateFase2($grand_total_fase_1, $tahun, $bulan);
        $perhitunganFase3 = $this->ltkHelper->calculateFase3($perhitunganFase2['hasil_fase_2'], $tahun, $bulan, $id_kpc);
        
        $hasilFase2 = "Rp " . number_format(round($perhitunganFase2['hasil_fase_2']), 0, '', '.');
        $hasilFase3 = "Rp " . number_format(round($perhitunganFase3['hasil_fase_3']), 0, '', '.');

        foreach ($rutin as $item) {
            $item->hasil_perhitungan_fase_1 = $hasilFase1PerBulan;
            $item->hasil_perhitungan_fase_1_raw = $grand_total_fase_1;
            $item->rumus_fase_2 = '(Total Produksi LTK Kantor LPU(prod meterai dibagi 10) / Total Produksi Jaskug Nasional(prod meterai dibagi 10)) x Hasil Perhitungan Fase 1';
            $item->total_produksi_ltk_kantor_lpu_prod_materai_dibagi_10 = $perhitunganFase2['total_produksi_ltk_kantor_lpu_prod_materai_dibagi_10'] ?? 0;
            $item->produksi_jaskug_nasional = $perhitunganFase2['produksi_jaskug_nasional'] ?? 0;
            $item->hasil_perhitungan_fase_2 = $hasilFase2;
            $item->hasil_perhitungan_fase_2_raw = $perhitunganFase2['hasil_fase_2'] ?? 0;
            $item->rumus_fase_3 = '(Produksi KCP LPU A / Total Produksi LTK Kantor LPU) x Hasil Perhitungan Fase 2';
            $item->produksi_kcp_lpu_a = $perhitunganFase3['produksi_kcp_lpu_a'] ?? 0;
            $item->total_produksi_ltk_kantor_lpu = $perhitunganFase3['total_produksi_ltk_kantor_lpu'] ?? 0;
            $item->hasil_perhitungan_fase_3 = $hasilFase3;
            $item->hasil_perhitungan_fase_3_raw = $perhitunganFase3['hasil_fase_3'] ?? 0;
        }
    }

    // ✅ PRIVATE METHOD: Process NPP
    private function processNPP(&$rutin, $tahun, $bulan, $kodeRekening)
    {
        $npp = Npp::where('id_rekening_biaya', $kodeRekening)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->first();

        $lastTwoDigits = substr($kodeRekening, -2);
        $produksiRek = ["06", "07", "08", "09"];
        $pendapatanRek = ["10", "11"];
        $sumField = null;
        $sumFieldKCP = null;

        if (in_array($lastTwoDigits, $pendapatanRek)) {
            $sumField = 'jml_pendapatan';
            $sumFieldKCP = 'bsu_bruto';
        } elseif (in_array($lastTwoDigits, $produksiRek)) {
            $sumField = 'jml_produksi';
            $sumFieldKCP = 'bilangan';
        }

        $produksi_nasional = 0;
        $produksi = 0;
        $kpcTotal = 2460;

        if ($sumField) {
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

        foreach ($rutin as $item) {
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

            $biayaPerNpp = $kpcTotal > 0 ? ($proporsi / $kpcTotal) : 0;
            $item->biaya_per_npp = "Rp " . number_format($biayaPerNpp, 0, '', '.');
            $item->biaya_per_npp_raw = $biayaPerNpp;
        }
    }

    // ✅ PRIVATE METHOD: Process RUTIN
    private function processRutin(&$rutin)
    {
        // Tidak ada logic tambahan untuk RUTIN
        // Hanya return data biasa
    }

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
                'data.*.verifikasi' => 'nullable|numeric', // ✅ Ubah ke numeric untuk handle float
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
                if (!isset($data['id_verifikasi_biaya_rutin_detail'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], 400);
                }

                $id_verifikasi_biaya_rutin_detail = $data['id_verifikasi_biaya_rutin_detail'];
                
                // Temukan entri VerifikasiBiayaRutinDetail
                $biaya_rutin_detail = VerifikasiBiayaRutinDetail::find($id_verifikasi_biaya_rutin_detail);

                // Cek apakah entri ditemukan
                if (!$biaya_rutin_detail) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya rutin tidak ditemukan'], 404);
                }

                // ✅ LOGIKA BARU: Handle float dan format Indonesia/internasional
                $verifikasiInput = $data['verifikasi'] ?? null;
                
                if ($verifikasiInput === null || $verifikasiInput === '' || $verifikasiInput === 0 || $verifikasiInput === '0') {
                    // Jika kosong/null, gunakan pelaporan
                    $verifikasiValue = $biaya_rutin_detail->pelaporan;
                } else {
                    // ✅ Deteksi format dan convert ke float
                    if (is_numeric($verifikasiInput)) {
                        // Jika sudah numeric (string atau number), langsung convert ke float
                        // Contoh: "3353438.574866114" atau 3353438.574866114
                        $verifikasiValue = (float) $verifikasiInput;
                    } elseif (is_string($verifikasiInput)) {
                        // Jika string dengan format Indonesia (Rp, koma, titik)
                        // Contoh: "Rp 3.353.438,57" atau "3.353.438,57"
                        
                        // Hapus prefix Rp dan spasi
                        $cleaned = str_replace(['Rp.', 'Rp', ' '], '', $verifikasiInput);
                        
                        // Deteksi format: cek apakah ada koma
                        if (strpos($cleaned, ',') !== false) {
                            // Format Indonesia: 3.353.438,57
                            // Hapus titik (pemisah ribuan), ganti koma dengan titik (desimal)
                            $cleaned = str_replace(['.', ','], ['', '.'], $cleaned);
                            $verifikasiValue = (float) $cleaned;
                        } else {
                            // Format internasional: 3353438.574866114 atau 3,353,438.57
                            // Hapus koma (pemisah ribuan)
                            $cleaned = str_replace(',', '', $cleaned);
                            $verifikasiValue = (float) $cleaned;
                        }
                    } else {
                        // Fallback: gunakan pelaporan
                        $verifikasiValue = $biaya_rutin_detail->pelaporan;
                    }
                }
                
                $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                // Update entri
                $biaya_rutin_detail->update([
                    'verifikasi' => $verifikasiValue,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                ]);

                // Tambahkan entri yang diperbarui ke array hasil
                $updatedData[] = [
                    'id' => $biaya_rutin_detail->id,
                    'verifikasi' => "Rp " . number_format($verifikasiValue, 2, ',', '.'),

                    'verifikasi_raw' => $verifikasiValue,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'pelaporan' => "Rp " . number_format($biaya_rutin_detail->pelaporan, 2, ',', '.'),

                    'source' => ($verifikasiInput === null || $verifikasiInput === '' || $verifikasiInput === 0) ? 'pelaporan' : 'input',
                ];
            }

            // Kembalikan respon sukses
            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Verifikasi Biaya Rutin',
                'modul' => 'Biaya Rutin',
                'id_user' => Auth::user()->id,
            ]);

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            Log::error('Verifikasi error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
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

    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bulan' => 'required|integer|min:1|max:12',
                'tahun' => 'required|integer',
                'nopend' => 'required|string|exists:kpc,nomor_dirian',
                'kategoribiaya' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $bulan = $request->bulan;
            $tahun = $request->tahun;
            $nopend = $request->nopend;
            $kategoribiaya = $request->kategoribiaya;
            $koderekening = $request->kode_rekening;

            $kategoriMapping = [
                1 => 'BIAYA PENJUALAN',
                2 => 'BIAYA OPERASI',
                3 => 'BIAYA UMUM',
            ];

            // Query data
            $query = VerifikasiBiayaRutinDetail::select(
                'verifikasi_biaya_rutin_detail.id',
                'verifikasi_biaya_rutin.id_regional',
                'verifikasi_biaya_rutin.id_kprk',
                'verifikasi_biaya_rutin.id_kpc',
                'verifikasi_biaya_rutin.tahun as tahun_anggaran',
                'verifikasi_biaya_rutin.triwulan',
                'verifikasi_biaya_rutin_detail.kategori_biaya',
                'rekening_biaya.kode_rekening as koderekening',
                'rekening_biaya.nama as nama_rekening',
                'verifikasi_biaya_rutin_detail.bulan',
                'verifikasi_biaya_rutin_detail.bilangan',
                'verifikasi_biaya_rutin_detail.verifikasi',
                'verifikasi_biaya_rutin_detail.pelaporan as nominal',
                'verifikasi_biaya_rutin.id_status as status',
                'verifikasi_biaya_rutin_detail.keterangan',
                'verifikasi_biaya_rutin_detail.lampiran'
            )
            ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
            ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
            ->join('kpc', 'verifikasi_biaya_rutin.id_kpc', '=', 'kpc.id')
            ->when($tahun, function ($q) use ($tahun) {
                $q->where('verifikasi_biaya_rutin.tahun', $tahun);
            })
            ->when($bulan, function ($q) use ($bulan) {
                $q->where('verifikasi_biaya_rutin_detail.bulan', $bulan);
            })
            ->when($nopend, function ($q) use ($nopend) {
                $q->where('kpc.nomor_dirian', $nopend);
            })
            ->when($koderekening, function ($q) use ($koderekening) {
                $q->where('rekening_biaya.kode_rekening', $koderekening);
            });

            if ($kategoribiaya && isset($kategoriMapping[$kategoribiaya])) {
                $query->where('verifikasi_biaya_rutin_detail.kategori_biaya', $kategoriMapping[$kategoribiaya]);
            }

            $data = $query->get();

            $formattedData = $data->map(function ($item) {
                return [
                    'id' => (string) $item->id,
                    'id_regional' => (string) $item->id_regional,
                    'id_kprk' => (string) $item->id_kprk,
                    'id_kpc' => (string) $item->id_kpc,
                    'tahun_anggaran' => (string) $item->tahun_anggaran,
                    'triwulan' => (string) $item->triwulan,
                    'kategori_biaya' => $item->kategori_biaya,
                    'koderekening' => (string) $item->koderekening,
                    'nama_rekening' => $item->nama_rekening,
                    'bulan' => (string) $item->bulan,
                    'bilangan' => (string) $item->bilangan,
                    'nominal' => (string) round($item->nominal),
                    'verifikasi' => (string) round($item->verifikasi),
                    'status' => (string) $item->status,
                    'keterangan' => $item->keterangan ?? '',
                    'lampiran' => $item->lampiran,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => $formattedData->isEmpty() ? 'Data tidak tersedia' : 'Data Tersedia',
                'total_data' => $formattedData->count(),
                'data' => $formattedData,
            ], 200);

        } catch (\Exception $e) {
            Log::error('getPerBulan error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'total_data' => 0,
                'data' => [],
            ], 500);
        }
    }

    public function apiLampiran(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bulan' => 'required|integer|min:1|max:12',
                'tahun' => 'required|integer',
                'nopend' => 'required|string|exists:kpc,nomor_dirian',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $bulan = str_pad($request->bulan, 2, '0', STR_PAD_LEFT);
            $tahun = $request->tahun;
            $nopend = $request->nopend;

            $data = VerifikasiBiayaRutinDetailLampiran::select(
                'verifikasi_biaya_rutin_detail_lampiran.id',
                'verifikasi_biaya_rutin_detail_lampiran.verifikasi_biaya_rutin_detail as id_biaya_detail',
                'verifikasi_biaya_rutin_detail_lampiran.nama_file',
                'verifikasi_biaya_rutin_detail.bulan',
                'kpc.nomor_dirian',
                'kpc.nama'
            )
            ->join('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin_detail_lampiran.verifikasi_biaya_rutin_detail', '=', 'verifikasi_biaya_rutin_detail.id')
            ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
            ->join('kpc', 'verifikasi_biaya_rutin.id_kpc', '=', 'kpc.id')
            ->where('verifikasi_biaya_rutin.tahun', $tahun)
            ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
            ->where('kpc.nomor_dirian', $nopend)
            ->whereNotNull('verifikasi_biaya_rutin_detail_lampiran.nama_file')
            ->where('verifikasi_biaya_rutin_detail_lampiran.nama_file', '!=', '')
            ->get();

            return response()->json([
                'success' => true,
                'message' => $data->isEmpty() ? 'Data tidak tersedia' : 'Data Tersedia',
                'total_data' => $data->count(),
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiDownloadLampiran($id)
    {
        try {
            $lampiran = VerifikasiBiayaRutinDetailLampiran::find($id);
            
            if (!$lampiran || !$lampiran->nama_file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lampiran tidak ditemukan',
                ], 404);
            }

            $filePath = storage_path('app/public/lampiran/' . $lampiran->nama_file);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan',
                ], 404);
            }

            return response()->download($filePath, $lampiran->nama_file);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}