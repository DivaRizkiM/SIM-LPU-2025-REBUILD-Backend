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

class VerifikasiProduksiController extends Controller
{
    public function getPerTahun(Request $request)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'tahun_anggaran' => 'nullable|numeric',
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

            // Request parameters
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $tahun_anggaran = request()->get('tahun_anggaran', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');

            // Order mappings
            $defaultOrder = $getOrder ? $getOrder : "regional.nama ASC";
            $orderMappings = [
                'namaASC' => 'regional.nama ASC',
                'namaDESC' => 'regional.nama DESC',
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
            ];
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            $validOrderValues = implode(',', array_keys($orderMappings));

            // Validation for input parameters
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

            // Query with conditions
            $produksiQuery = Produksi::orderByRaw($order)
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
                    DB::raw('SUM(produksi.total_lbf_prognosa) as total_lbf_prognosa')
                )
                ->join('regional', 'produksi.id_regional', '=', 'regional.id')
                ->groupBy('produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran')
                ->offset($offset)
                ->limit($limit);

            // Search functionality
            if ($search !== '') {
                $produksiQuery->where(function ($query) use ($search) {
                    $query->where('regional.nama', 'like', "%$search%")
                        ->orWhere('produksi.triwulan', 'like', "%$search%")
                        ->orWhere('produksi.tahun_anggaran', 'like', "%$search%");
                });
            }

            // Filter by tahun_anggaran, triwulan, and status
            if ($tahun_anggaran !== '') {
                $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
            }
            if ($triwulan !== '') {
                $produksiQuery->where('produksi.triwulan', $triwulan);
            }
            if ($status !== '') {
                $produksiQuery->where('produksi.id_status', $status);
            }

            // Get the data
            $produksi = $produksiQuery->get();

            // Grand total calculation
            $grand_total = $produksi->sum('total_lpu') + $produksi->sum('total_lpk') + $produksi->sum('total_lbf');
            $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');

            // Fetch status once and assign it to the items
            $statusItems = Produksi::whereIn('id_regional', $produksi->pluck('id_regional'))
                ->whereIn('triwulan', $produksi->pluck('triwulan'))
                ->whereIn('tahun_anggaran', $produksi->pluck('tahun_anggaran'))
                ->get()
                ->keyBy(function ($item) {
                    return $item->id_regional . '-' . $item->triwulan . '-' . $item->tahun_anggaran;
                });

            $produksi = $produksi->map(function ($item) use ($statusItems) {
                // Fetch total produksi for each item
                $total_produksi = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                    ->where('produksi.id_regional', $item->id_regional)
                    ->where('produksi.triwulan', $item->triwulan)
                    ->where('produksi.tahun_anggaran', $item->tahun_anggaran)
                    ->sum('produksi_detail.pelaporan');

                // Format total produksi
                $item->total_produksi = "Rp " . number_format(round($total_produksi), 0, '', '.');

                // Get the status based on the predefined criteria
                $statusKey = $item->id_regional . '-' . $item->triwulan . '-' . $item->tahun_anggaran;
                $statusId = 7; // Default status

                if ($statusItems->has($statusKey)) {
                    $statusItem = $statusItems->get($statusKey);

                    // Check if all status fields are 9
                    if ($statusItem->status_regional == 9 && $statusItem->status_lbf == 9 && $statusItem->status_lpu == 9 && $statusItem->status_kprk == 9) {
                        $statusId = 9; // All statuses are 9
                    }
                }

                // Assign the status name
                $status = Status::find($statusId);
                $item->status = $status ? $status->nama : 'Unknown';

                return $item;
            });

            // Return the response
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'grand_total' => $grand_total,
                'data' => $produksi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function getPerRegional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_anggaran' => 'nullable|numeric',
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
            $tahun_anggaran = request()->get('tahun_anggaran', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');

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

            $produksiQuery = ProduksiDetail::orderByRaw($order)
                ->select(
                    'produksi.id',
                    'produksi.triwulan',
                    'produksi.tahun_anggaran',
                    'regional.nama as nama_regional',
                    'regional.id as id_regional',
                    'kprk.id as id_kcu',
                    'kprk.nama as nama_kcu',
                    DB::raw('SUM(produksi_detail.pelaporan) as total_produksi'),
                    DB::raw("CASE
                            WHEN SUM(produksi_detail.pelaporan) != 0 AND SUM(produksi_detail.verifikasi) = 0 THEN 7
                            ELSE 9
                        END as status_id")
                )
                ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->join('kprk', 'produksi.id_kprk', '=', 'kprk.id')
                ->join('regional', 'produksi.id_regional', '=', 'regional.id')
                ->groupBy('kprk.id', 'produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama')
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
                $produksiQuery->where('produksi.id_status', $status);
            }

            $produksi = $produksiQuery->get();

            // Format and return the response
            $produksi->each(function ($item) {
                // Format total produksi
                $item->total_produksi = "Rp " . number_format(round($item->total_produksi), 0, '', '.');

                // Retrieve status by status_id
                $status = Status::firstWhere('id', $item->status_id);

                // Assign status
                $item->status = $status->nama;
            });

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $produksi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function getPerKCU(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_anggaran' => 'nullable|numeric',
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
            $tahun_anggaran = request()->get('tahun_anggaran', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "produksi.id ASC";
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

            $produksiQuery = ProduksiDetail::orderByRaw($order)
                ->select(
                    'produksi.id as produksi_id',
                    'produksi.triwulan',
                    'produksi.tahun_anggaran',
                    'produksi.id_regional',
                    'produksi.id_kprk as id_kcu',
                    'produksi.id_kpc as id_kpc',
                    DB::raw('SUM(produksi_detail.pelaporan) as total_produksi'),
                    DB::raw("CASE
                            WHEN SUM(produksi_detail.pelaporan) != 0 AND SUM(produksi_detail.verifikasi) = 0 THEN 7
                            ELSE 9
                        END as status_id"),
                    'regional.nama as nama_regional',
                    'kprk.nama as nama_kcu',
                    'kpc.nama as nama_kpc'
                )
                ->leftJoin('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->leftJoin('regional', 'produksi.id_regional', '=', 'regional.id')
                ->leftJoin('kprk', 'produksi.id_kprk', '=', 'kprk.id')
                ->leftJoin('kpc', 'produksi.id_kpc', '=', 'kpc.id')
                ->groupBy('produksi.id', 'produksi.triwulan', 'produksi.tahun_anggaran', 'produksi.id_regional', 'produksi.id_kprk', 'produksi.id_kpc', 'regional.nama', 'kprk.nama', 'kpc.nama')
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $produksiQuery->where(function ($query) use ($search) {
                    $query->where('regional.nama', 'like', "%$search%")
                        ->orWhere('produksi.triwulan', 'like', "%$search%")
                        ->orWhere('produksi.tahun_anggaran', 'like', "%$search%")
                        ->orWhere('kprk.nama', 'like', "%$search%")
                        ->orWhere('kpc.nama', 'like', "%$search%");
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

            if ($status !== '') {
                $produksiQuery->where('produksi.status_kprk', $status);
            }

            $produksi = $produksiQuery->get();

            // Format total_produksi to Rupiah format and set the status
            $produksi->each(function ($item) {
                $item->total_produksi = "Rp " . number_format(round($item->total_produksi), 0, '', '.');
                $status = Status::firstWhere('id', $item->status_id);
                $item->status = $status ? $status->nama : 'Unknown';
            });

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => request()->get('order', ''),
                'search' => request()->get('search', ''),
                'data' => $produksi,
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
            $id_produksi = request()->get('id_produksi', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
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
            $defaultOrder = $getOrder ? $getOrder : "produksi_detail.kategori_produksi ASC";
            $orderMappings = [
                'kodeproduksiASC' => 'rekening_produksi.kodeproduksi ASC',
                'kodeproduksiDESC' => 'rekening_produksi.kodeproduksi DESC',
                'namaASC' => 'rekening_produksi.nama ASC',
                'namaDESC' => 'rekening_produksi.nama DESC',
            ];
            // dd($request->id_produksi);

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
            $produksiQuery = ProduksiDetail::orderByRaw($order)
            ->select(
                'produksi.id as id_produksi',
                'produksi_detail.id as id_produksi_detail',
                'produksi_detail.kode_rekening',
                'rekening_produksi.nama as nama_rekening',
                'produksi.triwulan',
                'produksi.tahun_anggaran',
                'produksi_detail.nama_bulan',
                'produksi_detail.kategori_produksi',
                'produksi_detail.jenis_produksi',
                'produksi_detail.keterangan',
                'produksi_detail.pelaporan',
                'produksi_detail.nama_bulan',
            )
            ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
            ->join('rekening_produksi', 'produksi_detail.kode_rekening', '=', 'rekening_produksi.kode_rekening')
            ->where('produksi.id', $request->id_produksi)
            ->where('produksi.id_kprk', $request->id_kcu)
            ->where('produksi.id_kpc', $request->id_kpc)
            ->groupBy(
                'produksi_detail.id',
                'produksi_detail.kode_rekening',
                'produksi_detail.jenis_produksi',
                'produksi_detail.nama_bulan',
                'produksi_detail.keterangan',
                'produksi_detail.pelaporan',
                'produksi_detail.kategori_produksi',
            )->distinct()
            ->get();

            $groupedProduksi = [];
            $laporanArray = [];
            foreach ($produksiQuery as $item) {
                $kodeRekening = $item->kode_rekening;
                $triwulan = $item->triwulan;
                $tahun = $item->tahun_anggaran;
                $produk_keterangan = $item->keterangan;
                $aktifitas = $item->jenis_produksi;
                $jenis_layanan = $item->kategori_produksi;
                $id_produksi_detail = $item->id_produksi_detail;
                $id_produksi = $item->id_produksi;
                $nama_bulan = $item->nama_bulan;
                $pelaporan = $item->pelaporan;

                $key = "{$kodeRekening}_{$aktifitas}_{$jenis_layanan}_{$produk_keterangan}";

                // Jika kunci belum ada dalam array groupedProduksi, inisialisasi dengan array kosong
                if (!isset($groupedProduksi[$key])) {
                    $groupedProduksi[$key] = [
                        'kode_rekening' => $kodeRekening,
                        'nama_rekening' => $item->nama_rekening,
                        'jenis_layanan' => $jenis_layanan,
                        'aktifitas' => $aktifitas,
                        'produk_keterangan' => $produk_keterangan,
                        'laporan' => $laporanArray, // Inisialisasi array laporan per kode rekening
                    ];
                } else {
                    // Jika sudah ada, Anda bisa melakukan update atau menambahkan ke dalam array laporan
                    $groupedProduksi[$key]['laporan'][] = $laporanArray; // Contoh penambahan laporan
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

            // Inisialisasi laporan untuk setiap bulan dalam triwulan
            for ($i = $bulanAwalTriwulan; $i <= $bulanAkhirTriwulan; $i++) {
                $bulanString = $bulanIndonesia[$i - 1];
                $bulan = $i;
                $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);

                // Set default values for the report
                $pelaporan = 'Rp. 0,00';
                $verifikasi = 'Rp. 0,00';
                $isLockStatus = false;
                $idProduksiDetail = null; // Initialize id_produksi_detail

                $getPelaporan = ProduksiDetail::where('nama_bulan', $bulan)
                ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->where('produksi.id', $request->id_produksi)
                ->where('produksi.id_kprk', $request->id_kcu)
                ->where('produksi.id_kpc', $request->id_kpc)
                ->where('kode_rekening', $kodeRekening)
                ->where('keterangan', $produk_keterangan)
                ->where('jenis_produksi', $aktifitas)
                ->where('kategori_produksi', $jenis_layanan)
                ->select('produksi_detail.id as id_produksi_detail', 'produksi_detail.pelaporan', 'produksi_detail.verifikasi') // Select necessary fields
                ->first();
                $id_produksi_detail=null;
                // If data is available, update the report values
                if ($getPelaporan) {
                    $pelaporan = 'Rp. ' . number_format(round($getPelaporan->pelaporan),0, '', '.');
                    $verifikasi = 'Rp. ' . number_format(round($getPelaporan->verifikasi),0, '', '.');
                    $id_produksi_detail = $getPelaporan->id_produksi_detail;
                    // Check if the id contains any alphabetic characters


                }

                // Check for lock status
                $isLock = LockVerifikasi::where('tahun', $tahun)->where('bulan', $bulan)->first();
                if ($isLock) {
                    $isLockStatus = $isLock->status;
                }

                // Create the report data to the array for the specific month
                $laporanArray[$bulan] = [
                    'id_produksi_detail' => $id_produksi_detail, // Use the id from the fetched data or null
                    'aktivitas' => $aktifitas,
                    'produk_keterangan' => $produk_keterangan,
                    'jenis_layanan' => $jenis_layanan,
                    'bulan_string' => $bulanString,
                    'bulan' => $bulan,
                    'pelaporan' => $pelaporan,
                    'verifikasi' => $verifikasi,
                    'isLock' => $isLockStatus
                ];
            }

            // Ensure to re-index the array to have a sequential index
            $groupedProduksi[$key]['laporan'] = array_values($laporanArray);
            $groupedProduksi = array_unique($groupedProduksi, SORT_REGULAR);


                        }
                        return response()->json([
                            'status' => 'SUCCESS',
                            'offset' => $offset,
                            'limit' => $limit,
                            'order' => $order,
                            'id_kcu' => $request->id_kcu,
                            'id_kpc' => $request->id_kpc,
                            'data' => array_values($groupedProduksi),
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
                        $userLog=[
                            'timestamp' => now(),
                            'aktifitas' =>'Update Simpling Produksi',
                            'modul' => 'Produksi',
                            'id_user' => Auth::user(),
                        ];

                        $userLog = UserLog::create($userLog);

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
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
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
                    $item->pelaporan = "Rp " . number_format(round($item->pelaporan),0, '', '.');
                    $item->verifikasi = "Rp " . number_format(round($item->verifikasi),0, '', '.');
                    $item->url_lampiran = env('ENV_CONFIG_PATH') . $item->lampiran;

                    $isLock = LockVerifikasi::where('tahun', $item->tahun_anggaran)->where('bulan',$bulan)->first();
                    if ($isLock) {
                        $isLockStatus = $isLock->status;
                    }
                }
            }
            if($isLockStatus == true){
                $produksi =[];
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
            $updatedData = [];

            // Iterasi melalui data yang diverifikasi
            foreach ($verifikasiData as $data) {
                // Cek struktur data yang benar
                if (!isset($data['id_produksi_detail']) || !isset($data['verifikasi'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], 400);
                }

                // Proses nilai verifikasi
                $id_produksi_detail = $data['id_produksi_detail'];
                $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
                $verifikasiFloat = round((float) $verifikasi);  // Membulatkan nilai float
                $verifikasiFormatted = (string) $verifikasiFloat; // Hilangkan pemisah ribuan (titik)
                $catatan_pemeriksa = $data['catatan_pemeriksa'] ?? '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                // Temukan entri ProduksiDetail
                $produksi_detail = ProduksiDetail::find($id_produksi_detail);

                // Cek apakah entri ditemukan
                if (!$produksi_detail) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail produksi tidak ditemukan'], 404);
                }

                // Update entri
                $produksi_detail->update([
                    'verifikasi' => $verifikasiFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                ]);

                // Tambahkan entri yang diperbarui ke array hasil
                $updatedData[] = $produksi_detail;
            }
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Verifikasi Produksi',
                'modul' => 'Produksi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);

            // Kembalikan respon sukses
            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            // Kembalikan respon error
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }


}
