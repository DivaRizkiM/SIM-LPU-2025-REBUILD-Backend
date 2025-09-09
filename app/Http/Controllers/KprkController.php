<?php

namespace App\Http\Controllers;

use App\Models\Kprk;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class KprkController extends Controller
{
    // public function index(Request $request)
    // {
    //     try {
    //         // Ambil parameter offset, limit, dan order dari permintaan
    //         $offset = $request->get('offset', 0);
    //         $limit = $request->get('limit', 10);
    //         $search = $request->get('search', '');
    //         $getOrder = $request->get('order', '');

    //         // Tentukan aturan urutan default dan pemetaan urutan
    //         $defaultOrder = $getOrder ? $getOrder : "id ASC";
    //         $orderMappings = [
    //             'idASC' => 'kprk.id ASC',
    //             'idDESC' => 'kprk.id DESC',
    //             'namaASC' => 'kprk.nama ASC',
    //             'namaDESC' => 'kprk.nama DESC',
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

    //         // Query data Kprk dengan offset, limit, dan pencarian
    //         $query = Kprk::query();

    //         if ($search !== '') {
    //             $query->where('nama', 'like', "%$search%");
    //         }

    //         // Query kabupaten/kota with search condition if search keyword is provided
    //         $kprkssQuery = Kprk::leftJoin('regional', 'kprk.id_regional', '=', 'regional.id')
    //             ->select('kprk.*', 'regional.nama as nama_regional')
    //             ->orderByRaw($order);
    //         $total_data = $kprkssQuery->count();
    //         if ($search !== '') {
    //             $kprkssQuery->where(function ($query) use ($search) {
    //                 $query->where('kprk.nama', 'like', "%$search%")
    //                     ->orWhere('regional.nama', 'like', "%$search%");
    //             });
    //         }

    //         $kprks = $kprkssQuery
    //             ->offset($offset)
    //             ->limit($limit)->get();

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data' => $total_data,
    //             'data' => $kprks,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }
    public function index(Request $request)
    {
        try {
            // Ambil parameter dari permintaan
            $offset = $request->get('offset');
            $limit = $request->get('limit');
            $page = $request->get('page');
            $perPage = $request->get('per-page');
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $loopCount = $request->get('loopCount');

               // Default nilai jika page, per-page, atau loopCount tidak disediakan
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

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = "kprk.id ASC";
            $orderMappings = [
                'idASC' => 'kprk.id ASC',
                'idDESC' => 'kprk.id DESC',
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'namaregionalASC' => 'regional.nama ASC',
                'namaregionalDESC' => 'regional.nama DESC',
            ];

            // Atur urutan berdasarkan pemetaan atau gunakan urutan default
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

            // Query data KPRK dengan offset, limit, dan pencarian
            $query = Kprk::leftJoin('regional', 'kprk.id_regional', '=', 'regional.id')
                ->select('kprk.*', 'regional.nama as nama_regional');

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('kprk.nama', 'like', "%$search%")
                      ->orWhere('regional.nama', 'like', "%$search%");
                });
            }

            // Hitung total data sebelum penerapan limit dan offset
            $total_data = $query->count();

            // Ambil data dengan order, offset, dan limit
            $data = $query->orderByRaw($order)
                ->offset($offset)
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
                'data' => $data,
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
                'id_regional' => 'required|numeric|exists:regional,id',
                'kode' => 'required',
                'nama' => 'required',
                'id_file' => 'required',
                'id_provinsi' => 'required|numeric|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|numeric|exists:kabupaten_kota,id',
                'id_kecamatan' => 'required|numeric|exists:kecamatan,id',
                'id_kelurahan' => 'required|numeric|exists:kelurahan,id',
                'longitude' => 'required',
                'latitude' => 'required',
                'tgl_sinkronisasi' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            // Buat data Kprk baru

            $kprk = Kprk::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create KCU',
                'modul' => 'KCU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kprk], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            // Cari data Kprk berdasarkan ID
            $kprk = Kprk::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $kprk]);
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
            // Cari data Kprk berdasarkan ID
            $kprk = Kprk::where('id_regional', $request->id_regional)->get();
            return response()->json(['status' => 'SUCCESS', 'data' => $kprk]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required',
                'kode' => 'required',
                'nama' => 'required',
                'id_file' => 'required',
                'id_provinsi' => 'required',
                'id_kabupaten_kota' => 'required',
                'id_kecamatan' => 'required',
                'id_kelurahan' => 'required',
                'longitude' => 'required',
                'latitude' => 'required',
                'tgl_sinkronisasi' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            // Temukan dan perbarui data Kprk yang ada
            $kprk = Kprk::where('id', $id)->first();
            $kprk->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update KCU',
                'modul' => 'KCU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kprk]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Temukan dan hapus data Kprk berdasarkan ID
            $kprk = Kprk::where('id', $id)->first();
            $kprk->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete KCU',
                'modul' => 'KCU',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kprk deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
