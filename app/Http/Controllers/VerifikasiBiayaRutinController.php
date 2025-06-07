<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\UserLog;
use App\Models\Npp;
use App\Models\ProduksiNasional;
use App\Models\ProduksiDetail;
use App\Models\VerifikasiBiayaRutin;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\VerifikasiBiayaRutinDetailLampiran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LockVerifikasi;

use ZipArchive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use ZipStream\ZipStream;
// use ZipStream\Option\Archive as ArchiveOptions;



class VerifikasiBiayaRutinController extends Controller
{
    public function getPerTahun(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric', // Menyatakan bahwa tahun bersifat opsional dan harus berupa angka
                'triwulan' => 'nullable|numeric|in:1,2,3,4', // Menyatakan bahwa triwulan bersifat opsional, harus berupa angka, dan nilainya hanya boleh 1, 2, 3, atau 4
                'status' => 'nullable|string|in:7,9', // Menyatakan bahwa status bersifat opsional, harus berupa string, dan nilainya hanya boleh "aktif" atau "nonaktif"
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
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "nama ASC";
            $orderMappings = [
                'namaASC' => 'regional.nama ASC',
                'namaDESC' => 'regional.nama DESC',
                'triwulanASC' => 'verifikasi_biaya_rutin.triwulan ASC',
                'triwulanDESC' => 'verifikasi_biaya_rutin.triwulan DESC',
                'tahunASC' => 'verifikasi_biaya_rutin.tahun ASC',
                'tahunDESC' => 'verifikasi_biaya_rutin.tahun DESC',
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

            // $rutinQuery = VerifikasiBiayaRutin::orderByRaw($order)
            //     ->select('verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama as nama_regional', DB::raw('SUM(verifikasi_biaya_rutin.total_biaya) as total_biaya'))
            //     ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
            //     ->groupBy('verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun');

                $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select('verifikasi_biaya_rutin.id', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama as nama_regional', 'regional.id as id_regional', DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_biaya'))
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->groupBy('verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun');
            $total_data = $rutinQuery->count();
            if ($search !== '') {
                $rutinQuery->where(function ($query) use ($search) {
                    $query->where('verifikasi_biaya_rutin.id', 'like', "%$search%")
                          ->orWhere('verifikasi_biaya_rutin.id_regional', 'like', "%$search%")
                          ->orWhere('verifikasi_biaya_rutin.triwulan', 'like', "%$search%")
                          ->orWhere('verifikasi_biaya_rutin.tahun', 'like', "%$search%")
                          ->orWhere('verifikasi_biaya_rutin.total_biaya', 'like', "%$search%")
                          ->orWhere('verifikasi_biaya_rutin.id_status', 'like', "%$search%")
                          ->orWhere('regional.nama', 'like', "%$search%");
                });
            }
            // Menambahkan kondisi WHERE berdasarkan variabel $tahun, $triwulan, dan $status
            if ($tahun !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
            }

            if ($triwulan !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            }
            if ($status !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_status', $status);
            }

            $rutin = $rutinQuery
                ->offset($offset)
                ->limit($limit)->get();

            $grand_total = $rutin->sum('total_biaya');
            // $grand_total = "Rp " . number_format($grand_total, 0, '', '.');
            $grand_total = "Rp " . number_format($grand_total, 2, ',', '.');
            // $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');
            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($rutin as $item) {
                $item->total_biaya = "Rp " . number_format(round($item->total_biaya), 0, '', '.');
                // Ambil VerifikasiBiayaRutin dengan kriteria tertentu
                $getBiayaRutin = VerifikasiBiayaRutin::where('tahun', $item->tahun)
                    ->where('id_regional', $item->id_regional)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getBiayaRutin adalah 9
                $semuaStatusSembilan = $getBiayaRutin->every(function ($biayaRutin) {
                    return $biayaRutin->id_status == 9;
                });

                // Jika semua status adalah 9, ambil status dari tabel Status
                if ($semuaStatusSembilan) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
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
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $id_regional = request()->get('id_regional', '');
            $tahun = request()->get('tahun', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');
            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'verifikasi_biaya_rutin.triwulan ASC',
                'triwulanDESC' => 'verifikasi_biaya_rutin.triwulan DESC',
                'tahunASC' => 'verifikasi_biaya_rutin.tahun ASC',
                'tahunDESC' => 'verifikasi_biaya_rutin.tahun DESC',
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
            $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select('kprk.jumlah_kpc_lpu','verifikasi_biaya_rutin.id', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama as nama_regional', 'regional.id as id_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_biaya'))
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->groupBy('kprk.id', 'verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama');
            $total_data = $rutinQuery->count();
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
            if ($id_regional !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_regional', $id_regional);
            }
            if ($tahun !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
            }

            if ($triwulan !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            }

            if ($status !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_status', $status);
            }

            $rutin = $rutinQuery
                ->offset($offset)
                ->limit($limit)->get();

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($rutin as $item) {
                $jumlah_lpu = $item->jumlah_kpc_lpu;
                $jumlah_pengawas = 0;

                if ($jumlah_lpu <= 10) {
                    $jumlah_pengawas = 1;
                } elseif ($jumlah_lpu <= 20) {
                    $jumlah_pengawas = 2;
                } elseif ($jumlah_lpu <= 30) {
                    $jumlah_pengawas = 3;
                } elseif ($jumlah_lpu <= 40) {
                    $jumlah_pengawas = 4;
                } else {
                    $jumlah_pengawas = 5;
                }
                 $item->total_biaya = "Rp " . number_format(round($item->total_biaya), 0, '', '.');
                 $item->jumlah_pengawas = $jumlah_pengawas;

                // Ambil VerifikasiBiayaRutin dengan kriteria tertentu
                $getBiayaRutin = VerifikasiBiayaRutin::where('tahun', $item->tahun)
                    ->where('id_regional', $item->id_regional)
                    ->where('id_kprk', $item->id_kcu)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // dd($item);

                // Periksa apakah semua status dalam $getBiayaRutin adalah 9
                $semuaStatusSembilan = $getBiayaRutin->every(function ($biayaRutin) {
                    return $biayaRutin->id_status == 9;
                });

                // Jika semua status adalah 9, ambil status dari tabel Status
                if ($semuaStatusSembilan) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
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
            $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select('verifikasi_biaya_rutin.id as id_verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama as nama_regional', 'regional.id as id_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', 'kpc.id as id_kpc', 'kpc.nama as nama_kpc', DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_biaya'))
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
                ->join('kpc', 'verifikasi_biaya_rutin.id_kpc', '=', 'kpc.id')
                ->groupBy('kpc.id', 'kprk.id', 'verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama');
            $total_data = $rutinQuery->count();
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

            if ($status !== '') {
                // Anda perlu menyesuaikan kondisi WHERE ini sesuai dengan struktur tabel dan kondisi yang diinginkan.
                // Misalnya: $rutinQuery->where('status', $status);
            }
            $rutin = $rutinQuery
                ->offset($offset)
                ->limit($limit)->get();

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($rutin as $item) {
                 $item->total_biaya = "Rp " . number_format(round($item->total_biaya), 0, '', '.');

                // Ambil VerifikasiBiayaRutin dengan kriteria tertentu
                $getRutin = VerifikasiBiayaRutinDetail::where('id_verifikasi_biaya_rutin', $item->id_verifikasi_biaya_rutin)
                    ->where('pelaporan', '<>', 0.00)
                    ->where('verifikasi', 0.00)
                    ->first();

                $statusId = 9; // Default status 9

                if ($getRutin) {
                    $statusId = 7;
                }

                // Ambil status berdasarkan hasil pengecekan
                $status = Status::firstWhere('id', $statusId);

                // Atur status pada item saat ini
                $item->status = $status->nama;
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

    public function getPerKPC(Request $request)
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $getOrder = request()->get('order', '');
            $id_verifikasi_biaya_rutin = request()->get('id_verifikasi_biaya_rutin', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
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
            $defaultOrder = $getOrder ? $getOrder : "rekening_biaya.kode_rekening ASC";
            $orderMappings = [
                'koderekeningASC' => 'rekening_biaya.koderekening ASC',
                'koderekeningDESC' => 'rekening_biaya.koderekening DESC',
                'namaASC' => 'rekening_biaya.nama ASC',
                'namaDESC' => 'rekening_biaya.nama DESC',
            ];
            // dd($request->id_verifikasi_biaya_rutin);

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
            $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select(
                    // 'verifikasi_biaya_rutin.id as id_verifikasi_biaya_rutin',
                    'rekening_biaya.kode_rekening',
                    'rekening_biaya.nama as nama_rekening',
                    'verifikasi_biaya_rutin.triwulan',
                    'verifikasi_biaya_rutin.tahun',
                    'verifikasi_biaya_rutin_detail.bulan',
                    'verifikasi_biaya_rutin_detail.lampiran',
                )
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->where('verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', $request->id_verifikasi_biaya_rutin)
                ->where('verifikasi_biaya_rutin.id_kprk', $request->id_kcu)
                ->where('verifikasi_biaya_rutin.id_kpc', $request->id_kpc)
                ->groupBy('rekening_biaya.kode_rekening', 'verifikasi_biaya_rutin_detail.bulan')
                ->get();
                                // Initialize the $lampiran variable


            $groupedRutin = [];
            $laporanArray = [];
            foreach ($rutinQuery as $item) {

                $kodeRekening = $item->kode_rekening;
                $triwulan = $item->triwulan;
                $tahun = $item->tahun;

                // Jika kode_rekening belum ada dalam array groupedRutin, inisialisasikan dengan array kosong
                if (!isset($groupedRutin[$kodeRekening])) {
                    $groupedRutin[$kodeRekening] = [
                        // 'id_verifikasi_biaya_rutin' => $item->id_verifikasi_biaya_rutin,
                        'kode_rekening' => $kodeRekening,
                        'nama_rekening' => $item->nama_rekening,
                        'laporan' => $laporanArray, // Inisialisasi array laporan per kode rekening
                    ];
                }

                // Tentukan bulan-bulan berdasarkan triwulan
                $bulanAwalTriwulan = ($triwulan - 1) * 3 + 1;
                $bulanAkhirTriwulan = $bulanAwalTriwulan + 2;

                // Ubah format bulan dari angka menjadi nama bulan dalam bahasa Indonesia
                $bulanIndonesia = [
                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
                ];

                // Bersihkan $laporanArray sebelum iterasi
                $laporanArray = [];

                for ($i = $bulanAwalTriwulan; $i <= $bulanAkhirTriwulan; $i++) {
                    // Ubah format bulan dari angka menjadi nama bulan dalam bahasa Indonesia
                    $bulanString = $bulanIndonesia[$i - 1];
                    $bulan = $i;
                    $getPelaporan = VerifikasiBiayaRutinDetail::select(
                        DB::raw('SUM(pelaporan) as total_pelaporan'),
                        DB::raw('SUM(verifikasi) as total_verifikasi'))
                        ->where('bulan', $bulan)
                        ->where('id_rekening_biaya', $kodeRekening)
                        ->where('id_verifikasi_biaya_rutin', $request->id_verifikasi_biaya_rutin)
                        ->get();
                        $getlampiran = VerifikasiBiayaRutinDetail::select('verifikasi_biaya_rutin_detail.lampiran','verifikasi_biaya_rutin_detail_lampiran.nama_file')
                        ->leftJoin('verifikasi_biaya_rutin_detail_lampiran', 'verifikasi_biaya_rutin_detail.id', '=', 'verifikasi_biaya_rutin_detail_lampiran.verifikasi_biaya_rutin_detail')
                            ->where('bulan', $bulan)
                            ->where('id_rekening_biaya', $kodeRekening)
                            ->where('id_verifikasi_biaya_rutin', $request->id_verifikasi_biaya_rutin)
                            ->get();
                            $lampiran = 'N';
                            $nama_file = null;

                        // Check if any of the records have lampiran as 'Y'
                        if ($getlampiran->contains('lampiran', 'Y')) {
                            $lampiran = 'Y';
                            $nama_file = $getlampiran->first()->nama_file;
                        }



                    // Pastikan query menghasilkan data sebelum memprosesnya
                    if ($getPelaporan->isNotEmpty()) {
                        $pelaporan = 'Rp. ' . number_format(round($getPelaporan[0]->total_pelaporan), 0, '', '.');
                        $verifikasi = 'Rp. ' . number_format(round($getPelaporan[0]->total_verifikasi), 0, '', '.');
                    } else {
                        $pelaporan = 'Rp. 0';
                        $verifikasi = 'Rp. 0';
                    }
                    $isLock = LockVerifikasi::where('tahun', $tahun)->where('bulan',$bulan)->first();
                    $isLockStatus = false;
                    if ($isLock) {
                        $isLockStatus = $isLock->status;
                    }

                    // Tambahkan data ke dalam array laporan
                    $laporanArray[] = [
                        'bulan_string' => $bulanString,
                        'bulan' => $bulan,
                        'pelaporan' => $pelaporan,
                        'verifikasi' => $verifikasi,
                        'isLock' =>$isLockStatus,
                        'lampiran' => $lampiran,
                        'url_lampiran' => config('app.env_config_path') . $nama_file,
                    ];
                }

                // Tambahkan laporanArray ke dalam groupedRutin
                $groupedRutin[$kodeRekening]['laporan'] = $laporanArray;
            }
            $dataValues = array_values($groupedRutin);
            $total_data = count($dataValues);
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $order,
                'id_kcu' => $request->id_kcu,
                'id_kpc' => $request->id_kpc,
                'total_data' => $total_data,
                'data' => $dataValues,
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

            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Simpling Biaya Rutin',
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
            $bulanIndonesia = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            ];

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
                'verifikasi_biaya_rutin_detail_lampiran.nama_file'
            )
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
                ->join('kpc', 'verifikasi_biaya_rutin.id_kpc', '=', 'kpc.id')
                ->leftJoin('verifikasi_biaya_rutin_detail_lampiran', 'verifikasi_biaya_rutin_detail.id', '=', 'verifikasi_biaya_rutin_detail_lampiran.verifikasi_biaya_rutin_detail')
                ->where('verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', $request->id_verifikasi_biaya_rutin)
                ->where('verifikasi_biaya_rutin_detail.id_rekening_biaya', $request->kode_rekening)
                ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
                ->where('verifikasi_biaya_rutin.id_kprk', $request->id_kcu)
                ->where('verifikasi_biaya_rutin.id_kpc', $request->id_kpc)
                ->get();

            // dd($rutin);
            $isLockStatus = false;
            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($rutin as $item) {
                $item->pelaporan = "Rp " . number_format(round($item->pelaporan), 0, '', '.');
                $item->verifikasi = "Rp " . number_format(round($item->verifikasi), 0, '', '.');
                $item->url_lampiran = config('app.env_config_path') . $item->nama_file;

                $isLock = LockVerifikasi::where('tahun', $item->tahun)->where('bulan',$bulan)->first();
                $npp = Npp::where('id_rekening_biaya',$item->kode_rekening)
                ->where('tahun',$item->tahun)
                ->where('bulan',$bulan)
                ->first();
                // (produksi outgoing kcp lpu / produksi outgoing nasional) x biaya npp)
                $produksi = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->where('produksi.tahun_anggaran', $item->tahun)
                ->where('produksi_detail.nama_bulan', $bulan)
                ->where('jenis_produksi','PENERIMAAN/OUTGOING')
                ->sum('produksi_detail.pelaporan');
                $produksi_nasional = ProduksiNasional::where('tahun', $item->tahun)
                ->where('bulan', $bulan)
                ->where('status','OUTGOING')
                ->sum('jml_pendapatan');


           // Calculate the proportion
           $proporsi = 0;

           if (($produksi ?? 0) != 0 && ($produksi_nasional ?? 0) != 0 && ($npp->bsu ?? 0) != 0) {
               $proporsi = ($produksi / $produksi_nasional) * $npp->bsu;
           }

            // Format the NPP and proportion values
            $item->npp = "Rp " . number_format(($npp->bsu ?? 0), 0, '', '.');
            $item->proporsi = "Rp " . number_format(($proporsi), 0, '', '.');
            // $item->npp = "Rp " .($npp->bsu ?? 0);
            // $item->proporsi = "Rp " .($proporsi);
            $item->biaya_per_npp = $item->pelaporan;
                if ($isLock) {
                    $isLockStatus = $isLock->status;
                }
            }
            if($isLockStatus == true){
                $rutin =[];
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
            return response()->stream(function() use ($tempFile) {
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
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Verifikasi Biaya Rutin',
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


}
