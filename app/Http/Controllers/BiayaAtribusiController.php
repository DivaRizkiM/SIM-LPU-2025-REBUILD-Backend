<?php

namespace App\Http\Controllers;

use App\Models\BiayaAtribusi;
use App\Models\Kpc;
use App\Models\BiayaAtribusiDetail;
use App\Models\Kprk;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\ProduksiDetail;
use App\Models\ProduksiNasional;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LockVerifikasi;
use App\Models\UserLog;
use Illuminate\Support\Facades\Artisan;

class BiayaAtribusiController extends Controller
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

            $defaultOrder = $getOrder ? $getOrder : "regional.nama ASC";
            $orderMappings = [
                'namaASC' => 'regional.nama ASC',
                'namaDESC' => 'regional.nama DESC',
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

            $atribusiQuery = BiayaAtribusi::orderByRaw($order)
                ->select('biaya_atribusi.id_regional', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran', 'regional.nama as nama_regional', DB::raw('SUM(biaya_atribusi.total_biaya) as total_biaya'))
                ->join('regional', 'biaya_atribusi.id_regional', '=', 'regional.id')
                ->groupBy('biaya_atribusi.id_regional', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran');

            $total_data = $atribusiQuery->count();

            if ($search !== '') {
                $atribusiQuery->where(function ($query) use ($search) {
                    $query->where('regional.nama', 'like', "%$search%")
                        ->orWhere('biaya_atribusi.id_regional', 'like', "%$search%")
                        ->orWhere('biaya_atribusi.triwulan', 'like', "%$search%")
                        ->orWhere('biaya_atribusi.tahun_anggaran', 'like', "%$search%");
                });
            }


            // Menambahkan kondisi WHERE berdasarkan variabel $tahun, $triwulan, dan $status
            if ($tahun !== '') {
                $atribusiQuery->where('biaya_atribusi.tahun_anggaran', $tahun);
            }

            if ($triwulan !== '') {
                $atribusiQuery->where('biaya_atribusi.triwulan', $triwulan);
            }
            if ($status !== '') {
                $atribusiQuery->where('biaya_atribusi.id_status', $status);
            }

            $atribusi = $atribusiQuery->offset($offset)
                ->limit($limit)->get();

            $grand_total = $atribusi->sum('total_biaya');
            $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');
            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($atribusi as $item) {
                $item->total_biaya = "Rp " . number_format(round($item->total_biaya), 0, '', '.');

                // Ambil BiayaAtribusi dengan kriteria tertentu
                $getBiayaAtribusi = BiayaAtribusi::where('tahun_anggaran', $item->tahun_anggaran)
                    ->where('id_regional', $item->id_regional)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getBiayaAtribusi adalah 9
                $semuaStatusSembilan = $getBiayaAtribusi->every(function ($biayaAtribusi) {
                    return $biayaAtribusi->id_status == 9;
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
                'data' => $atribusi,
            ]);
        } catch (\Exception $e) {
            // dd($e);
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
            $atribusiQuery = BiayaAtribusiDetail::orderByRaw($order)
                ->select('kprk.jumlah_kpc_lpu', 'biaya_atribusi.id', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran', 'regional.nama as nama_regional', 'regional.id as id_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', DB::raw('SUM(biaya_atribusi_detail.pelaporan) as total_biaya'))
                ->join('biaya_atribusi', 'biaya_atribusi_detail.id_biaya_atribusi', '=', 'biaya_atribusi.id')
                ->join('kprk', 'biaya_atribusi.id_kprk', '=', 'kprk.id')
                ->join('regional', 'biaya_atribusi.id_regional', '=', 'regional.id')
                ->groupBy('kprk.id', 'biaya_atribusi.id_regional', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran', 'regional.nama');
            $total_data = $atribusiQuery->count();
            if ($search !== '') {
                $atribusiQuery->where('kprk.nama', 'like', "%$search%");
            }
            if ($search !== '') {
                $atribusiQuery->where(function ($query) use ($search) {
                    $query->where('regional.nama', 'like', "%$search%")
                        ->orWhere('biaya_atribusi.id_regional', 'like', "%$search%")
                        ->orWhere('biaya_atribusi.triwulan', 'like', "%$search%")
                        ->orWhere('biaya_atribusi.tahun_anggaran', 'like', "%$search%")
                        ->orWhere('kprk.nama', 'like', "%$search%");
                });
            }

            if ($id_regional !== '') {
                $atribusiQuery->where('biaya_atribusi.id_regional', $id_regional);
            }
            if ($tahun !== '') {
                $atribusiQuery->where('biaya_atribusi.tahun_anggaran', $tahun);
            }

            if ($triwulan !== '') {
                $atribusiQuery->where('biaya_atribusi.triwulan', $triwulan);
            }

            if ($status !== '') {
                $atribusiQuery->where('biaya_atribusi.id_status', $status);
            }

            $atribusi = $atribusiQuery->offset($offset)
                ->limit($limit)->get();

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($atribusi as $item) {
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

                // Ambil BiayaAtribusi dengan kriteria tertentu
                $getBiayaAtribusi = BiayaAtribusi::where('tahun_anggaran', $item->tahun_anggaran)
                    ->where('id_regional', $item->id_regional)
                    ->where('id_kprk', $item->id_kcu)
                    ->where('triwulan', $item->triwulan)
                    ->get();
                // dd($getBiayaAtribusi);

                // Periksa apakah semua status dalam $getBiayaAtribusi adalah 9
                $semuaStatusSembilan = $getBiayaAtribusi->every(function ($biayaAtribusi) {
                    return $biayaAtribusi->id_status == 9;
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
                'data' => $atribusi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function getPerKCU(Request $request)
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $getOrder = request()->get('order', '');
            $id_biaya_atribusi = request()->get('id_biaya_atribusi', '');
            $id_kcu = request()->get('id_kcu', '');
            $validator = Validator::make($request->all(), [
                'id_biaya_atribusi' => 'nullable|numeric|exists:biaya_atribusi,id',
                'id_kcu' => 'nullable|numeric|exists:kprk,id',
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
            // dd($request->id_biaya_atribusi);

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
            $atribusiQuery = BiayaAtribusiDetail::orderByRaw($order)
                ->select(
                    'rekening_biaya.kode_rekening',
                    'rekening_biaya.nama as nama_rekening',
                    'biaya_atribusi.triwulan',
                    'biaya_atribusi.tahun_anggaran',
                    'biaya_atribusi_detail.bulan',
                    'biaya_atribusi_detail.keterangan',
                )
                ->join('biaya_atribusi', 'biaya_atribusi_detail.id_biaya_atribusi', '=', 'biaya_atribusi.id')
                ->join('rekening_biaya', 'biaya_atribusi_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->where('biaya_atribusi_detail.id_biaya_atribusi', $request->id_biaya_atribusi)
                ->where('biaya_atribusi.id_kprk', $request->id_kcu)
                ->groupBy('rekening_biaya.kode_rekening', 'biaya_atribusi_detail.bulan')
                ->get();
            // dd($atribusiQuery);

            $groupedAtribusi = [];
            $laporanArray = [];
            foreach ($atribusiQuery as $item) {
                // if ($item->nama_rekening === 'Angkutan Pos Setempat                             ') {
                //     $item->nama_rekening = 'Atribusi Biaya Operasi';
                // }
                $kodeRekening = $item->kode_rekening;
                $triwulan = $item->triwulan;
                $tahun = $item->tahun_anggaran;
                $ketarangan = $item->keterangan;

                // Jika kode_rekening belum ada dalam array groupedAtribusi, inisialisasikan dengan array kosong
                if (!isset($groupedAtribusi[$kodeRekening])) {
                    $groupedAtribusi[$kodeRekening] = [
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

                // Bersihkan $laporanArray sebelum iterasi
                $laporanArray = [];

                for ($i = $bulanAwalTriwulan; $i <= $bulanAkhirTriwulan; $i++) {
                    // Ubah format bulan dari angka menjadi nama bulan dalam bahasa Indonesia
                    $bulanString = $bulanIndonesia[$i - 1];
                    $bulan = $i;
                    $getPelaporan = BiayaAtribusiDetail::select(
                        DB::raw('SUM(pelaporan) as total_pelaporan'),
                        DB::raw('SUM(verifikasi) as total_verifikasi')
                    )
                        ->where('bulan', $bulan)
                        ->where('id_rekening_biaya', $kodeRekening)
                        ->where('id_biaya_atribusi', $request->id_biaya_atribusi)
                        ->get();

                    // Pastikan query menghasilkan data sebelum memprosesnya
                    if ($getPelaporan->isNotEmpty()) {
                        $pelaporan = 'Rp. ' . number_format(round($getPelaporan[0]->total_pelaporan), 0, '', '.');
                        $verifikasi = 'Rp. ' . number_format(round($getPelaporan[0]->total_verifikasi), 0, '', '.');
                    } else {
                        $pelaporan = 'Rp. 0';
                        $verifikasi = 'Rp. 0';
                    }
                    $isLock = LockVerifikasi::where('tahun', $tahun)->where('bulan', $bulan)->first();
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
                        'isLock' => $isLockStatus
                    ];
                }

                // Tambahkan laporanArray ke dalam groupedAtribusi
                $groupedAtribusi[$kodeRekening]['laporan'] = $laporanArray;
            }
            $dataValues = array_values($groupedAtribusi);
            $total_data = count($dataValues);

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $order,
                'id_biaya_atribusi' => $request->id_biaya_atribusi,
                'id_kcu' => $request->id_kcu,
                'total_data' => $total_data,
                'data' => $dataValues,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    // public function getDetail(Request $request)
    // {
    //     // href="/backend/verifikasi_biaya_atribusi_detail/update?a=2270020231&b=5102050004&c=1&d=2023&e=01"

    //     // href="/backend/verifikasi_biaya_atribusi_detail/update?a=2270020231&b=5102050004&c=1&d=2023&e=02"
    //     try {

    //         $id_biaya_atribusi = request()->get('id_biaya_atribusi', '');
    //         $kode_rekening = request()->get('kode_rekening', '');
    //         $bulan = request()->get('bulan', '');
    //         $id_kcu = request()->get('id_kcu', '');
    //         $validator = Validator::make($request->all(), [
    //             'bulan' => 'required|numeric|max:12',
    //             'kode_rekening' => 'required|numeric|exists:rekening_biaya,id',
    //             'id_biaya_atribusi' => 'required|numeric|exists:biaya_atribusi,id',
    //             'id_kcu' => 'required|numeric|exists:kprk,id',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'ERROR',
    //                 'message' => 'Invalid input parameters',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }
    //         $bulanIndonesia = [
    //             'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    //             'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    //         ];

    //         $atribusi = BiayaAtribusiDetail::select(
    //             'biaya_atribusi_detail.id as id_biaya_atribusi_detail',
    //             'rekening_biaya.kode_rekening',
    //             'rekening_biaya.nama as nama_rekening',
    //             'biaya_atribusi.tahun_anggaran',
    //             DB::raw("CONCAT('" . $bulanIndonesia[$request->bulan - 1] . "') AS periode"),
    //             'biaya_atribusi_detail.keterangan',
    //             'biaya_atribusi_detail.lampiran',
    //             'biaya_atribusi_detail.pelaporan',
    //             'biaya_atribusi_detail.verifikasi',
    //             'biaya_atribusi_detail.catatan_pemeriksa',
    //             'kprk.nama as nama_kcu',
    //             'kprk.jumlah_kpc_lpu',
    //             'kprk.jumlah_kpc_lpk',
    //             'kprk.id as id_kprk'
    //         )

    //             ->where('biaya_atribusi_detail.id_biaya_atribusi', $request->id_biaya_atribusi)
    //             ->where('biaya_atribusi_detail.id_rekening_biaya', $request->kode_rekening)
    //             ->where('biaya_atribusi_detail.bulan', $request->bulan)
    //             ->where('biaya_atribusi.id_kprk', $request->id_kcu)
    //             ->join('biaya_atribusi', 'biaya_atribusi_detail.id_biaya_atribusi', '=', 'biaya_atribusi.id')
    //             ->join('rekening_biaya', 'biaya_atribusi_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
    //             ->join('kprk', 'biaya_atribusi.id_kprk', '=', 'kprk.id')
    //             ->get();

    //         // dd($atribusi);
    //         $isLockStatus = false;
    //         // Mengubah format total_biaya menjadi format Rupiah
    //         foreach ($atribusi as $item) {
    //             // if ($item->keterangan === 'Angkutan Pos Setempat/') {
    //             //     $item->keterangan = 'Atribusi Biaya Operasi/';
    //             // }
    //             $jumlah_lpu = $item->jumlah_kpc_lpu;
    //             $jumlah_lpk = $item->jumlah_kpc_lpk;
    //             $total_kcp = $jumlah_lpu + $jumlah_lpk;



    //             $kode_rekening = $item->kode_rekening;
    //             $bulan2 = str_pad($bulan, 2, '0', STR_PAD_LEFT);

    //             $biaya = VerifikasiBiayaRutinDetail::join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
    //                 ->where('verifikasi_biaya_rutin_detail.id_rekening_biaya', $kode_rekening)
    //                 ->where('verifikasi_biaya_rutin_detail.bulan', $bulan2)
    //                 ->where('verifikasi_biaya_rutin.tahun', $item->tahun_anggaran)
    //                 ->where('verifikasi_biaya_rutin.id_kprk', $request->id_kcu)
    //                 ->sum('verifikasi_biaya_rutin_detail.pelaporan');

    //                 $pendapatan = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //                 ->where('produksi.tahun_anggaran', $item->tahun_anggaran)
    //                 ->where('produksi_detail.nama_bulan', $bulan)
    //                 ->where('produksi.id_kprk', $request->id_kcu)
    //                 ->sum('produksi_detail.pelaporan');

    //                 $pendapatan_nasional = ProduksiNasional::where('tahun', $item->tahun_anggaran)
    //                 ->where('bulan', $bulan)
    //                 ->sum('jml_pendapatan');
    //             // $rumus_1 = $biaya * ($pendapatan / $pendapatan_nasional);
    //             // $rumus_2 =
    //             // $hasil =
    //             $jarak = kpc::where('id_kprk',$request->id_kcu)->sum('jarak_ke_kprk');
    //             $alokasiKcu = 0;
    //             $biayaKcp = 0;


    //             if (($jumlah_lpu ?? 0) != 0 && ($total_kcp ?? 0) != 0 && ($item->pelaporan ?? 0) != 0) {
    //                 $alokasiKcu = ($jumlah_lpu / $total_kcp) * $item->pelaporan;
    //             }

    //             if (($jarak ?? 0) != 0 && ($total_kcp ?? 0) != 0 && $alokasiKcu != 0) {
    //                 $biayaKcp = $jarak / $total_kcp * $alokasiKcu;
    //             }



    //             $item->pelaporan = "Rp " . number_format(round($item->pelaporan), 0, '', '.');
    //             $item->verifikasi = "Rp " . number_format(round($item->verifikasi), 0, '', '.');
    //             $item->url_lampiran = config('app.env_config_path') . $item->lampiran;
    //             $item->rumus_alokasi_kcu = "( Jumlah KCP LPU / Total KCP ( KCP LPK + KCP LPU) * Nominal Verifikasi )";
    //             $item->rumus_biaya_kcp = "( Jarak KCP Ke KCU / Total KCP * Nominal Alokasi Biaya Per KCU )";
    //             $item->alokasi_kcu = "Rp " . number_format(round($alokasiKcu), 0, '', '.');
    //             $item->biaya_kcp = "Rp " . number_format(round($biayaKcp), 0, '', '.');

    //             $isLock = LockVerifikasi::where('tahun', $item->tahun_anggaran)->where('bulan',$bulan)->first();
    //             if ($isLock) {
    //                 $isLockStatus = $isLock->status;
    //             }

    //         }
    //         if($isLockStatus == true){
    //             $atribusi =[];
    //         }

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'storage' => storage_path(),
    //             'isLock' => $isLockStatus,
    //             'data' => $atribusi,

    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function getDetail(Request $request)
    {
        try {
            // Validasi parameter input
            $validator = Validator::make($request->all(), [
                'bulan' => 'required|numeric|max:12',
                'kode_rekening' => 'required|numeric|exists:rekening_biaya,id',
                'id_biaya_atribusi' => 'required|numeric|exists:biaya_atribusi,id',
                'id_kcu' => 'required|numeric|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Parameter yang digunakan
            $bulan = $request->get('bulan');
            $tahunAnggaran = $request->get('tahun');
            $idBiayaAtribusi = $request->get('id_biaya_atribusi');
            $kodeRekening = $request->get('kode_rekening');
            $idKcu = $request->get('id_kcu');
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

            // Query data utama
            $atribusi = BiayaAtribusiDetail::select(
                'biaya_atribusi_detail.id as id_biaya_atribusi_detail',
                'rekening_biaya.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'biaya_atribusi.tahun_anggaran',
                DB::raw("CONCAT('" . $bulanIndonesia[$bulan - 1] . "') AS periode"),
                'biaya_atribusi_detail.keterangan',
                'biaya_atribusi_detail.lampiran',
                'biaya_atribusi_detail.pelaporan',
                'biaya_atribusi_detail.verifikasi',
                'biaya_atribusi_detail.catatan_pemeriksa',
                'kprk.nama as nama_kcu',
                'kprk.jumlah_kpc_lpu',
                'kprk.jumlah_kpc_lpk',
                'kprk.id as id_kprk'
            )
                ->join('biaya_atribusi', 'biaya_atribusi_detail.id_biaya_atribusi', '=', 'biaya_atribusi.id')
                ->join('rekening_biaya', 'biaya_atribusi_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->join('kprk', 'biaya_atribusi.id_kprk', '=', 'kprk.id')
                ->where([
                    ['biaya_atribusi_detail.id_biaya_atribusi', $idBiayaAtribusi],
                    ['biaya_atribusi_detail.id_rekening_biaya', $kodeRekening],
                    ['biaya_atribusi_detail.bulan', $bulan],
                    ['biaya_atribusi.id_kprk', $idKcu],
                ])
                ->get();

            // Hitung data tambahan
            $isLockStatus = false;
            foreach ($atribusi as $item) {
                if ($item->kode_rekening == '5102030104') {
                    $keteranganParts = explode('/', $item->keterangan);
                    if (count($keteranganParts) > 1) {
                        $detailPart = end($keteranganParts);
                        preg_match('/-([A-Z0-9]+)-(\d+\.?\d*)\s*Kg\.?/i', $detailPart, $matches);
                        if (count($matches) > 2) {
                            $item->rute = $matches[1];
                            $item->bobot_kiriman = (float) $matches[2];
                        } else {
                            $item->rute = 'N/A';
                            $item->bobot_kiriman = 0;
                        }
                    } else {
                        $item->rute = 'N/A';
                        $item->bobot_kiriman = 0;
                    }
                }

                $totalKcp = $item->jumlah_kpc_lpu + $item->jumlah_kpc_lpk;

                $alokasiKcu = ($item->jumlah_kpc_lpu / $totalKcp) * ($item->pelaporan ?? 0);
                $jarak = kpc::where('id_kprk', $idKcu)->sum('jarak_ke_kprk');
                $biayaKcp = ($jarak / $totalKcp) * $alokasiKcu;

                $item->pelaporan = "Rp " . number_format(round($item->pelaporan), 0, '', '.');
                $item->verifikasi = "Rp " . number_format(round($item->verifikasi), 0, '', '.');
                $item->alokasi_kcu = "Rp " . number_format(round($alokasiKcu), 0, '', '.');
                $item->biaya_kcp = "Rp " . number_format(round($biayaKcp), 0, '', '.');
                $item->url_lampiran = config('app.env_config_path') . $item->lampiran;

                $isLock = LockVerifikasi::where([
                    ['tahun', $item->tahun_anggaran],
                    ['bulan', $bulan],
                ])->first();

                if ($isLock) {
                    $isLockStatus = $isLock->status;
                }
            }

            if ($isLockStatus) {
                $atribusi = [];
            }

            return response()->json([
                'status' => 'SUCCESS',
                'isLock' => $isLockStatus,
                'data' => $atribusi,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifikasi(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_biaya_atribusi_detail' => 'required|string|exists:biaya_atribusi_detail,id',
                'data.*.verifikasi' => 'required|string',
                'data.*.catatan_pemeriksa' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $verifikasiData = $request->input('data');
            $updatedData = [];

            foreach ($verifikasiData as $data) {
                if (!isset($data['id_biaya_atribusi_detail']) || !isset($data['verifikasi'])) {
                    DB::rollBack();
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], 400);
                }

                $id_biaya_atribusi_detail = $data['id_biaya_atribusi_detail'];

                $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
                $verifikasiFloat = round((float) $verifikasi);
                $verifikasiFormatted = (string) $verifikasiFloat;
                $catatan_pemeriksa = isset($data['catatan_pemeriksa']) ? $data['catatan_pemeriksa'] : '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();
                $biaya_atribusi_detail = BiayaAtribusiDetail::with('biayaAtribusi')->find($id_biaya_atribusi_detail);

                $totalKcp = Kprk::where('id', $biaya_atribusi_detail->biayaAtribusi->id_kprk)->sum('jumlah_kpc_lpu');
                $pelaporanFormatted = str_replace(['Rp.', ',', '.'], '', $biaya_atribusi_detail->pelaporan);
                $tahun = $biaya_atribusi_detail->biayaAtribusi->tahun_anggaran;
                $verifikasiPerKcp = $verifikasi / $totalKcp;
                if (!$biaya_atribusi_detail) {
                    DB::rollBack();
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya atribusi tidak ditemukan'], 404);
                }

                $biaya_atribusi_detail->update([
                    'verifikasi' => $verifikasiFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                ]);

                $biayaRutins = VerifikasiBiayaRutinDetail::where('bulan', $biaya_atribusi_detail->bulan)
                    ->where('id_rekening_biaya', $biaya_atribusi_detail->id_rekening_biaya)
                    ->whereHas('verifikasiBiayaRutin', function ($query) use ($tahun) {
                        $query->where('tahun', $tahun);
                    })->get();

                foreach ($biayaRutins as $biayaRutin) {
                    $biayaRutin->update([
                        'verifikasi' => $verifikasiPerKcp
                    ]);
                }

                $updatedData[] = $biaya_atribusi_detail;
            }

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Verifikasi Atribusi',
                'modul' => 'Atribusi',
                'id_user' => Auth::user(),
            ];

            UserLog::create($userLog);
            DB::commit();
            Artisan::call('cache:clear');

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
