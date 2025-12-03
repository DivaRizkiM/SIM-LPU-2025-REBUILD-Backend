<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\Mitra;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class KpcController extends Controller
{
    // public function index(Request $request)
    // {
    //     try {
    //         // Ambil parameter offset, limit, dan order dari permintaan
    //         $offset = $request->get('offset', 0);
    //         $limit = $request->get('limit', 10);
    //         $search = $request->get('search', '');
    //         $getOrder = $request->get('order', '');
    //         $id_regional = $request->get('id_regional', '');
    //         $id_kprk = $request->get('id_kprk', '');
    //         $id_provinsi = $request->get('id_provinsi', '');
    //         $id_kabupaten_kota = $request->get('id_kabupaten_kota', '');
    //         $id_kecamatan = $request->get('id_kecamatan', '');
    //         $id_kelurahan = $request->get('id_kelurahan', '');
    //         $id_penyelenggara = $request->get('id_penyelenggara', '');
    //         $jenis_kantor = $request->get('jenis_kantor', '');

    //         // Tentukan aturan urutan default dan pemetaan urutan
    //         $defaultOrder = $getOrder ? $getOrder : "id ASC";
    //         $orderMappings = [
    //             'idASC' => 'kpc.id ASC',
    //             'idDESC' => 'kpc.id DESC',
    //             'namaASC' => 'kpc.nama ASC',
    //             'namaDESC' => 'kpc.nama DESC',
    //             'namakprkASC' => 'kprk.nama ASC',
    //             'namakprkDESC' => 'kprk.nama DESC',
    //             'namaregionalASC' => 'regional.nama ASC',
    //             'namaregionalDESC' => 'regional.nama DESC',
    //             // Tambahkan pemetaan urutan lain jika diperlukan
    //         ];

    //         // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
    //         $order = $orderMappings[$getOrder] ?? $defaultOrder;

    //         // Validasi aturan untuk parameter masukan
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


    //         $kpcssQuery = Kpc::leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
    //             ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
    //             ->leftJoin('provinsi', 'kpc.id_provinsi', '=', 'provinsi.id')
    //             ->leftJoin('kabupaten_kota', 'kprk.id_kabupaten_kota', '=', 'kabupaten_kota.id')
    //             ->leftJoin('kecamatan', 'kpc.id_kecamatan', '=', 'kecamatan.id')
    //             ->leftJoin('kelurahan', 'kpc.id_kelurahan', '=', 'kelurahan.id')
    //             ->select('kpc.id as id_kpc', 'kpc.*',
    //                 'regional.nama as nama_regional', 'kprk.nama as nama_kprk',
    //                 'provinsi.id as id_provinsi',
    //                 'kabupaten_kota.id as id_kabupaten_kota',
    //                 'kecamatan.id as id_kecamatan',
    //                 'kelurahan.id as id_kelurahan')
    //             ->orderByRaw($order);
    //         $total_data = $kpcssQuery->count();
    //         if ($search !== '') {
    //             $kpcssQuery->where(function ($query) use ($search) {
    //                 $query->where('kpc.nama', 'like', "%$search%")
    //                     ->orWhere('regional.nama', 'like', "%$search%");
    //             });
    //         }
    //         $id_regional = $request->get('id_regional', '');
    //         $id_kprk = $request->get('id_kprk', '');
    //         $id_provinsi = $request->get('id_provinsi', '');
    //         $id_kabupaten_kota = $request->get('id_kabupaten_kota', '');
    //         $id_kecamatan = $request->get('id_kecamatan', '');
    //         $id_kelurahan = $request->get('id_kelurahan', '');
    //         $id_penyelenggara = $request->get('id_penyelenggara', '');

    //         if ($id_provinsi) {
    //             $kpcssQuery->where('kpc.id_provinsi', $id_provinsi);
    //         }
    //         if ($id_kabupaten_kota) {
    //             $kpcssQuery->where('kpc.id_kabupaten_kota', $id_kabupaten_kota);
    //         }
    //         if ($id_kecamatan) {
    //             $kpcssQuery->where('kpc.id_kecamatan', $id_kecamatan);
    //         }
    //         if ($id_kelurahan) {
    //             $kpcssQuery->where('kpc.id_kelurahan', $id_kelurahan);
    //         }
    //         if ($id_regional) {
    //             $kpcssQuery->where('kpc.id_regional', $id_regional);
    //         }
    //         if ($id_kprk) {
    //             $kpcssQuery->where('kprk.id', $id_kprk);
    //         }
    //         if ($id_penyelenggara) {
    //             $kpcssQuery->where('kpc.id', $id_penyelenggara);
    //         }
    //         if ($jenis_kantor) {
    //             $kpcssQuery->where('kpc.jenis_kantor', $jenis_kantor);
    //         }

    //         $kpcs = $kpcssQuery->offset($offset)
    //             ->limit($limit)->get();

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data' => $total_data,
    //             'data' => $kpcs]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }
    public function index(Request $request)
{
    try {
        // Ambil parameter offset, limit, dan order dari permintaan
        $page = $request->get('page');
        $perPage = $request->get('per-page');
        $search = $request->get('search', '');
        $getOrder = $request->get('order', '');
        $offset = $request->get('offset');
        $limit = $request->get('limit');
        $loopCount = $request->get('loopCount');
        if (is_null($page) && is_null($perPage) && is_null($loopCount)) {
            $offset = $offset ?? 0; // Default offset
            $limit = $limit ?? 10; // Default limit
        } else {
            $page = $page ?? 1; // Default halaman ke 1
            $perPage = $perPage ?? 10; // Default per halaman ke 10
            $loopCount = $loopCount ?? 1; // Default loopCount ke 1

            // Hitung offset dan limit berdasarkan page, per-page, dan loopCount
            $offset = ($page - 1) * $perPage * $loopCount;
            $limit = $perPage * $loopCount;
        }

        // Ambil parameter tambahan untuk filter
        $filterParams = [
            'id_regional' => $request->get('id_regional'),
            'id_kprk' => $request->get('id_kprk'),
            'id_provinsi' => $request->get('id_provinsi'),
            'id_kabupaten_kota' => $request->get('id_kabupaten_kota'),
            'id_kecamatan' => $request->get('id_kecamatan'),
            'id_kelurahan' => $request->get('id_kelurahan'),
            'id_penyelenggara' => $request->get('id_penyelenggara'),
            'jenis_kantor' => $request->get('jenis_kantor'),
        ];

        // Tentukan aturan urutan default dan pemetaan urutan
        $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
        $orderMappings = [
            'idASC' => 'kpc.id ASC',
            'idDESC' => 'kpc.id DESC',
            'namaASC' => 'kpc.nama ASC',
            'namaDESC' => 'kpc.nama DESC',
            'namakprkASC' => 'kprk.nama ASC',
            'namakprkDESC' => 'kprk.nama DESC',
            'namaregionalASC' => 'regional.nama ASC',
            'namaregionalDESC' => 'regional.nama DESC',
        ];

        // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
        $order = $orderMappings[$getOrder] ?? $defaultOrder;

        // Validasi aturan untuk parameter masukan
        $validOrderValues = implode(',', array_keys($orderMappings));
        $rules = [
            'page' => 'integer|min:1|nullable',
            'per-page' => 'integer|min:1|nullable',
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1',
            'order' => "in:$validOrderValues",
            'loopCount' => 'integer|min:1|nullable',
        ];

        $validator = Validator::make([
            'offset' => $offset,
            'limit' => $limit,
            'page' => $page,
            'per-page' => $perPage,
            'order' => $getOrder,
        ], $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Query data KPC dengan join ke tabel terkait
        $kpcssQuery = Kpc::leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
            ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
            ->leftJoin('provinsi', 'kpc.id_provinsi', '=', 'provinsi.id')
            ->leftJoin('kabupaten_kota', 'kpc.id_kabupaten_kota', '=', 'kabupaten_kota.id')
            ->leftJoin('kecamatan', 'kpc.id_kecamatan', '=', 'kecamatan.id')
            ->leftJoin('kelurahan', 'kpc.id_kelurahan', '=', 'kelurahan.id')
            ->select(
                'kpc.id as id_kpc', 'kpc.*',
                'regional.nama as nama_regional', 'kprk.nama as nama_kprk',
                'provinsi.id as id_provinsi',
                'kabupaten_kota.id as id_kabupaten_kota',
                'kecamatan.id as id_kecamatan',
                'kelurahan.id as id_kelurahan'
            )
            ->orderByRaw($order);

        // Filter pencarian
        if ($search !== '') {
            $kpcssQuery->where(function ($query) use ($search) {
                $query->where('kpc.nama', 'like', "%$search%")
                      ->orWhere('regional.nama', 'like', "%$search%");
            });
        }

        // Filter tambahan berdasarkan parameter yang diterima
        foreach ($filterParams as $key => $value) {
            if ($value) {
                $kpcssQuery->where("kpc.$key", $value);
            }
        }

        // Hitung total data sebelum limit dan offset diterapkan
        $total_data = $kpcssQuery->count();

        // Ambil data dengan limit dan offset
        $kpcs = $kpcssQuery->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'offset' => $offset,
            'limit' => $limit,
            'page' => $page,
            'per-page' => $perPage,
            'order' => $getOrder,
            'search' => $search,
            'total_data' => $total_data,
            'data' => $kpcs,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => $e->getMessage(),
        ], 500);
    }
}

    public function store(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'required|exists:kprk,id',
                'nomor_dirian' => 'required',
                'nama' => 'required',
                'jenis_kantor' => 'required',
                'alamat' => 'required',
                'koordinat_longitude' => 'required|numeric',
                'koordinat_latitude' => 'required|numeric',
                'nomor_telpon' => 'nullable|numeric',
                'nomor_fax' => 'nullable|numeric',
                'id_provinsi' => 'required|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|exists:kabupaten_kota,id',
                'id_kecamatan' => 'required|exists:kecamatan,id',
                'id_kelurahan' => 'required|exists:kelurahan,id',
                'tipe_kantor' => 'required',
                'jam_kerja_senin_kamis' => 'nullable',
                'jam_kerja_jumat' => 'nullable',
                'jam_kerja_sabtu' => 'nullable',
                'frekuensi_antar_ke_alamat' => 'nullable',
                'frekuensi_antar_ke_dari_kprk' => 'nullable',
                'jumlah_tenaga_kontrak' => 'nullable|numeric',
                'kondisi_gedung' => 'nullable',
                'fasilitas_publik_dalam' => 'nullable',
                'fasilitas_publik_halaman' => 'nullable',
                'lingkungan_kantor' => 'nullable',
                'lingkungan_sekitar_kantor' => 'nullable',
                'tgl_sinkronisasi' => 'nullable|date',
                'id_user' => 'required|exists:users,id',
                'tgl_update' => 'nullable|date',
                'id_file' => 'nullable|exists:files,id',

            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            // Buat data Kpc baru
            $kpc = Kpc::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Add Kcp',
                'modul' => 'Kcp',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            // Cari data Kpc berdasarkan ID
            $kpc = Kpc::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 404);
        }
    }

    public function getByregional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_regional' => 'numeric|exists:regional,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }
            // Cari data Kpc berdasarkan ID
            $kpc = Kpc::where('id_regional', $request->id_regional)->get();
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 404);
        }
    }
    public function getBykprk(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_kprk' => 'numeric|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }
            // Cari data Kpc berdasarkan ID
            $kpc = Kpc::select('*', 'nomor_dirian as id')->where('id_kprk', $request->id_kprk)->get();
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'required|exists:kprk,id',
                'nomor_dirian' => 'required',
                'nama' => 'required',
                'jenis_kantor' => 'required',
                'alamat' => 'required',
                'koordinat_longitude' => 'required|numeric',
                'koordinat_latitude' => 'required|numeric',
                'nomor_telpon' => 'nullable|numeric',
                'nomor_fax' => 'nullable|numeric',
                'id_provinsi' => 'required|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|exists:kabupaten_kota,id',
                'id_kecamatan' => 'required|exists:kecamatan,id',
                'id_kelurahan' => 'required|exists:kelurahan,id',
                'tipe_kantor' => 'required',
                'jam_kerja_senin_kamis' => 'nullable',
                'jam_kerja_jumat' => 'nullable',
                'jam_kerja_sabtu' => 'nullable',
                'frekuensi_antar_ke_alamat' => 'nullable',
                'frekuensi_antar_ke_dari_kprk' => 'nullable',
                'jumlah_tenaga_kontrak' => 'nullable|numeric',
                'kondisi_gedung' => 'nullable',
                'fasilitas_publik_dalam' => 'nullable',
                'fasilitas_publik_halaman' => 'nullable',
                'lingkungan_kantor' => 'nullable',
                'lingkungan_sekitar_kantor' => 'nullable',
                'tgl_sinkronisasi' => 'nullable|date',
                'id_user' => 'required|exists:users,id',
                'tgl_update' => 'nullable|date',
                'id_file' => 'nullable|exists:files,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            // Temukan dan perbarui data Kpc yang ada
            $kpc = Kpc::where('id', $id)->first();
            $kpc->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Kcp',
                'modul' => 'Kcp',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Temukan dan hapus data Kpc berdasarkan ID
            $kpc = Kpc::where('id', $id)->first();
            $kpc->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Kcp',
                'modul' => 'Kcp',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kpc deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function map(Request $request)
    {
        try {
            // Ambil parameter offset, limit, dan order dari permintaan
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = $getOrder ? $getOrder : "id ASC";
            $orderMappings = [
                'idASC' => 'kpc.id ASC',
                'idDESC' => 'kpc.id DESC',
                'namaASC' => 'kpc.nama ASC',
                'namaDESC' => 'kpc.nama DESC',
                'namakprkASC' => 'kprk.nama ASC',
                'namakprkDESC' => 'kprk.nama DESC',
                'namaregionalASC' => 'regional.nama ASC',
                'namaregionalDESC' => 'regional.nama DESC',
                // Tambahkan pemetaan urutan lain jika diperlukan
            ];

            // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            // Validasi aturan untuk parameter masukan
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

            // Query data Kpc dengan offset, limit, dan pencarian
            $query = Kpc::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }

            // Query kabupaten/kota with search condition if search keyword is provided
            $kpcssQuery = Kpc::leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
                ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
                ->select('kpc.id', 'kpc.nama', 'kpc.koordinat_longitude', 'kpc.koordinat_latitude', 'regional.nama as nama_regional', 'kprk.nama as nama_kprk', 'kpc.id_regional', 'kpc.id_kprk');

            if ($search !== '') {
                $kpcssQuery->where(function ($query) use ($search) {
                    $query->where('kpc.nama', 'like', "%$search%")->where('kprk.nama', 'like', "%$search%")
                        ->orWhere('regional.nama', 'like', "%$search%");
                });
            }

            $kpcs = $kpcssQuery->get();
            $count = $kpcssQuery->count();
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'count' => $count,
                'search' => $search,
                'data' => $kpcs]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function apiList(Request $request)
    {
        try {
            $query = Kpc::with(['regional:id,nama', 'kprk:id,nama']);
            
            if ($request->has('id_regional')) {
                $query->where('id_regional', $request->id_regional);
            }
            
            if ($request->has('id_kprk')) {
                $query->where('id_kprk', $request->id_kprk);
            }
            
            $data = $query->orderBy('nomor_dirian')->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data Tersedia',
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

    public function apiDetail($id)
    {
        try {
            $data = Kpc::with(['regional', 'kprk'])->find($id);
            
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data Tersedia',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate QR Code untuk KPC
     * QR berisi URL ke endpoint detail KPC
     */
    public function generateQrCode($id)
    {
        try {
            $kpc = Kpc::findOrFail($id);
            
            // ✅ Generate UUID jika belum ada (simpan di database)
            if (!$kpc->qr_uuid) {
                $kpc->qr_uuid = Str::uuid()->toString();
                $kpc->save();
            }
            
            // ✅ URL yang di-encode ke QR (bisa diubah data KPC, QR tetap sama)
            $qrUrl = url("/api/kpc/qr/{$kpc->qr_uuid}");
            
            // ✅ Generate QR Code (format SVG/PNG)
            $qrCode = QrCode::size(300)
                ->format('svg')
                ->generate($qrUrl);
            
            return response()->json([
                'status' => 'SUCCESS',
                'data' => [
                    'id_kpc' => $kpc->id,
                    'nama_kpc' => $kpc->nama,
                    'qr_uuid' => $kpc->qr_uuid,
                    'qr_url' => $qrUrl,
                    'qr_code' => base64_encode($qrCode), // SVG as base64
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download QR Code sebagai file PNG
     */
    public function downloadQrCode($id)
    {
        try {
            $kpc = Kpc::findOrFail($id);
            
            if (!$kpc->qr_uuid) {
                $kpc->qr_uuid = Str::uuid()->toString();
                $kpc->save();
            }
            
            $qrUrl = url("/api/kpc/qr/{$kpc->qr_uuid}");
            
            // ✅ Generate QR Code as PNG
            $qrCode = QrCode::format('png')
                ->size(500)
                ->margin(2)
                ->generate($qrUrl);
            
            $filename = "QR_KPC_{$kpc->nomor_dirian}.png";
            
            return response($qrCode)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint yang dipanggil saat QR Code di-scan
     * Menampilkan profil KPC terbaru dari database
     */
    public function getByQrCode($uuid)
    {
        try {
            // ✅ Validasi format UUID
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Format UUID tidak valid'
                ], 400);
            }

            // ✅ Cari KPC berdasarkan UUID
            $kpc = Kpc::where('qr_uuid', $uuid)->first();
            
            if (!$kpc) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'QR Code tidak valid atau data tidak ditemukan',
                    'hint' => 'Pastikan QR Code sudah di-generate terlebih dahulu'
                ], 404);
            }

            // ✅ Load relasi secara manual untuk avoid error
            $kpc->load([
                'regional:id,nama',
                'kprk:id,nama',
                'provinsi:id,nama',
                'kabupaten:id,nama',
                'kecamatan:id,nama',
                'kelurahan:id,nama'
            ]);
            
            // ✅ Return data profil KPC dengan batas wilayah
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Data Profil KPC',
                'data' => [
                    'id' => $kpc->id,
                    'nomor_dirian' => $kpc->nomor_dirian,
                    'nama' => $kpc->nama,
                    'jenis_kantor' => $kpc->jenis_kantor,
                    'tipe_kantor' => $kpc->tipe_kantor,
                    'alamat' => $kpc->alamat,
                    'nomor_telpon' => $kpc->nomor_telpon,
                    'nomor_fax' => $kpc->nomor_fax,
                    'koordinat' => [
                        'latitude' => $kpc->koordinat_latitude,
                        'longitude' => $kpc->koordinat_longitude,
                    ],
                    // ✅ Struktur organisasi
                    'regional' => [
                        'id' => $kpc->regional?->id,
                        'nama' => $kpc->regional?->nama,
                    ],
                    'kprk' => [
                        'id' => $kpc->kprk?->id,
                        'nama' => $kpc->kprk?->nama,
                    ],
                    // ✅ Batas wilayah administratif
                    'wilayah' => [
                        'provinsi' => [
                            'id' => $kpc->provinsi?->id,
                            'nama' => $kpc->provinsi?->nama,
                        ],
                        'kabupaten_kota' => [
                            'id' => $kpc->kabupaten?->id,
                            'nama' => $kpc->kabupaten?->nama,
                        ],
                        'kecamatan' => [
                            'id' => $kpc->kecamatan?->id,
                            'nama' => $kpc->kecamatan?->nama,
                        ],
                        'kelurahan' => [
                            'id' => $kpc->kelurahan?->id,
                            'nama' => $kpc->kelurahan?->nama,
                        ],
                    ],
                    'jam_kerja' => [
                        'senin_kamis' => $kpc->jam_kerja_senin_kamis,
                        'jumat' => $kpc->jam_kerja_jumat,
                        'sabtu' => $kpc->jam_kerja_sabtu,
                    ],
                    'kondisi_gedung' => $kpc->kondisi_gedung,
                    'fasilitas_publik_dalam' => $kpc->fasilitas_publik_dalam,
                    'fasilitas_publik_halaman' => $kpc->fasilitas_publik_halaman,
                    'lingkungan_kantor' => $kpc->lingkungan_kantor ?? null,
                    'lingkungan_sekitar_kantor' => $kpc->lingkungan_sekitar_kantor ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Terjadi kesalahan sistem',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Generate QR Code untuk banyak KPC sekaligus (batch)
     */
    public function batchGenerateQrCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_kprk' => 'nullable|exists:kprk,id',
                'id_regional' => 'nullable|exists:regional,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => $validator->errors()
                ], 422);
            }

            $query = Kpc::query();
            
            if ($request->id_kprk) {
                $query->where('id_kprk', $request->id_kprk);
            }
            
            if ($request->id_regional) {
                $query->where('id_regional', $request->id_regional);
            }
            
            $kpcs = $query->get();
            
            $results = [];
            
            foreach ($kpcs as $kpc) {
                if (!$kpc->qr_uuid) {
                    $kpc->qr_uuid = Str::uuid()->toString();
                    $kpc->save();
                }
                
                $qrUrl = url("/api/kpc/qr/{$kpc->qr_uuid}");
                
                $results[] = [
                    'id_kpc' => $kpc->id,
                    'nama_kpc' => $kpc->nama,
                    'nomor_dirian' => $kpc->nomor_dirian,
                    'qr_uuid' => $kpc->qr_uuid,
                    'qr_url' => $qrUrl,
                ];
            }
            
            return response()->json([
                'status' => 'SUCCESS',
                'total' => count($results),
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
