<?php

namespace App\Http\Controllers;

use App\Models\Regional;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RegionalController extends Controller
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
    //             'idASC' => 'id ASC',
    //             'idDESC' => 'id DESC',
    //             'namaASC' => 'nama ASC',
    //             'namaDESC' => 'nama DESC',
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
    //             ], Response::HTTP_BAD_REQUEST);
    //         }

    //         // Query data regional dengan offset, limit, dan pencarian
    //         $query = Regional::query();

    //         if ($search !== '') {
    //             $query->where('nama', 'like', "%$search%");
    //         }

    //         $regionalQuery = $query->orderByRaw($order);

    //         $total_data = $query->count();
    //         $regional = $query->offset($offset)
    //             ->limit($limit)
    //             ->get();

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data' => $total_data,
    //             'data' => $regional,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }
    public function index(Request $request)
    {
        try {
            // Ambil parameter dari permintaan
            $page = $request->get('page');
            $perPage = $request->get('per-page');
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $offset = $request->get('offset');
            $limit = $request->get('limit');
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
            $defaultOrder = "regional.id ASC";
            $orderMappings = [
                'idASC' => 'regional.id ASC',
                'idDESC' => 'regional.id DESC',
                'namaASC' => 'regional.nama ASC',
                'namaDESC' => 'regional.nama DESC',
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
                'page' => $page,
                'per-page' => $perPage,
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'loopCount' => $loopCount,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Query data regional dengan offset, limit, dan pencarian
            $query = Regional::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }

            $total_data = $query->count();
            $data = $query->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'SUCCESS',
                'page' => $page,
                'per-page' => $perPage,
                'offset' => $offset,
                'limit' => $limit,
                'loopCount' => $loopCount,
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
                'kode' => 'required',
                'nama' => 'required',
                'id_file' => 'required',
                'id_provinsi' => 'required',
                'id_kabupaten_kota' => 'required',
                'id_kecamatan' => 'required',
                'id_kelurahan' => 'required',
                'longitude' => 'required',
                'latitude' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            // Buat data regional baru
            $regional = Regional::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Regional',
                'modul' => 'Regional',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $regional], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            // Cari data regional berdasarkan ID
            $regional = Regional::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $regional]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'kode' => 'required',
                'nama' => 'required',
                'id_file' => 'required',
                'id_provinsi' => 'required',
                'id_kabupaten_kota' => 'required',
                'id_kecamatan' => 'required',
                'id_kelurahan' => 'required',
                'longitude' => 'required',
                'latitude' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            // Temukan dan perbarui data regional yang ada
            $regional = Regional::where('id', $id)->first();
            $regional->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Regional',
                'modul' => 'Regional',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $regional]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Temukan dan hapus data regional berdasarkan ID
            $regional = Regional::where('id', $id)->first();
            $regional->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Regional',
                'modul' => 'Regional',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Regional deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
