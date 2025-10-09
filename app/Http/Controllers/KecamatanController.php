<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\Kecamatan;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class KecamatanController extends Controller
{
    // public function index()
    // {
    //     try {
    //         // Ambil parameter offset, limit, dan order dari permintaan
    //         $offset = request()->get('offset', 0);
    //         $limit = request()->get('limit', 10);
    //         $search = request()->get('search', '');
    //         $getOrder = request()->get('order', '');
    //         $idProvinsi = request()->get('id_provinsi', '');
    //         $idKab = request()->get('id_kabupaten_kota', '');

    //         // Tentukan aturan urutan default dan pemetaan urutan
    //         $defaultOrder = $getOrder ? $getOrder : "kecamatan.id ASC";
    //         $orderMappings = [
    //             'idASC' => 'kecamatan.id ASC',
    //             'idDESC' => 'kecamatan.id DESC',
    //             'namakecamatanASC' => 'kecamatan.nama ASC',
    //             'namakecamatanDESC' => 'kecamatan.nama DESC',
    //             'namaprovinsiASC' => 'provinsi.nama ASC',
    //             'namaprovinsiDESC' => 'provinsi.nama DESC',
    //             'namakabupatenASC' => 'kabupaten_kota.nama ASC',
    //             'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
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

    //         $kecamatansQuery = Kecamatan::leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
    //             ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
    //             ->select('kecamatan.*', 'kabupaten_kota.nama as nama_kabupaten_kota', 'provinsi.nama as nama_provinsi')
    //             ->orderByRaw($order);
    //         $total_data = $kecamatansQuery->count();
    //         // Filter by id_provinsi if provided
    //         if ($idProvinsi) {
    //             $kecamatansQuery->where('kabupaten_kota.id_provinsi', $idProvinsi);
    //         }
    //         if ($idKab) {
    //             $kecamatansQuery->where('kecamatan.id_kabupaten_kota', $idKab);
    //         }

    //         if ($search !== '') {
    //             $kecamatansQuery->where(function ($query) use ($search) {
    //                 $query->where('kecamatan.nama', 'like', "%$search%")
    //                     ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
    //                     ->orWhere('provinsi.nama', 'like', "%$search%");
    //             });
    //         }

    //         $kecamatans = $kecamatansQuery->offset($offset)
    //             ->limit($limit)->get();

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data' => $total_data,
    //             'data' => $kecamatans,
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
            $idProvinsi = $request->get('id_provinsi');
            $idKab = $request->get('id_kabupaten_kota');

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
            $defaultOrder = $getOrder ? $getOrder : "kecamatan.id ASC";
            $orderMappings = [
                'idASC' => 'kecamatan.id ASC',
                'idDESC' => 'kecamatan.id DESC',
                'namakecamatanASC' => 'kecamatan.nama ASC',
                'namakecamatanDESC' => 'kecamatan.nama DESC',
                'namaprovinsiASC' => 'provinsi.nama ASC',
                'namaprovinsiDESC' => 'provinsi.nama DESC',
                'namakabupatenASC' => 'kabupaten_kota.nama ASC',
                'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
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
                'id_provinsi' => 'integer|exists:provinsi,id|nullable',
                'id_kabupaten_kota' => 'integer|exists:kabupaten_kota,id|nullable',

            ];

            $validator = Validator::make([
                'page' => $page,
                'per-page' => $perPage,
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'id_provinsi' => $idProvinsi,
                'id_kabupaten_kota' => $idKab,
                'loopCount' => $loopCount,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Query kecamatan with search and filter conditions
            $query = Kecamatan::leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->select('kecamatan.*', 'kabupaten_kota.nama as nama_kabupaten_kota', 'provinsi.nama as nama_provinsi');

            if ($idProvinsi) {
                $query->where('kabupaten_kota.id_provinsi', $idProvinsi);
            }

            if ($idKab) {
                $query->where('kecamatan.id_kabupaten_kota', $idKab);
            }

            if ($search !== '') {
                $query->where(function ($query) use ($search) {
                    $query->where('kecamatan.nama', 'like', "%$search%")
                        ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                });
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
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function show($id)
    {
        try {
            $kecamatan = Kecamatan::leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->select('kecamatan.*', 'kabupaten_kota.nama as nama_kabupaten_kota', 'provinsi.nama as nama_provinsi')
                ->where('kecamatan.id', $id)->first();

            return response()->json(['status' => 'SUCCESS', 'data' => $kecamatan]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'required|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|exists:kabupaten_kota,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $kecamatan = Kecamatan::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Kecamatan',
                'modul' => 'Kecamatan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kecamatan], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'required|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|exists:kabupaten_kota,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $kecamatan = Kecamatan::where('id', $id)->first();
            $kecamatan->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Kecamatan',
                'modul' => 'Kecamatan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kecamatan]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $kecamatan = Kecamatan::where('id', $id)->first();
            $kecamatan->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Kecamatan',
                'modul' => 'Kecamatan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kecamatan deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function syncKecamatan(Request $request)
    {
        try {
            $endpoint = 'kecamatan';
            $userAgent = $request->header('User-Agent');

            // probe untuk mengetahui total data
            $perPage = 1000;
            $apiController = new ApiController();
            $probeReq = \Illuminate\Http\Request::create('/', 'GET', [
                'end_point' => $endpoint,
                'page' => 1,
                'per_page' => $perPage,
            ]);
            $firstResp = $apiController->makeRequest($probeReq);
            $total = $firstResp['total_data'] ?? (is_array($firstResp['data']) ? count($firstResp['data']) : 0);

            if ($total <= 0) {
                return response()->json(['status' => 'NO_DATA', 'message' => 'Tidak ada data untuk disinkronisasi'], 200);
            }

            $pages = (int) ceil($total / $perPage);

            // Log aktivitas user singkat
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Kecamatan',
                'modul' => 'Kecamatan',
                'id_user' => Auth::user(),
            ];
            UserLog::create($userLog);

            // Dispatch job per halaman
            for ($p = 1; $p <= $pages; $p++) {
                \App\Jobs\ProcessSyncKecamatanJob::dispatch($endpoint, $userAgent, $p, $perPage);
            }

            return response()->json([
                'status' => 'IN_PROGRESS',
                'message' => 'Sinkronisasi sedang diproses (jobs dispatched)',
                'total_records' => $total,
                'pages' => $pages,
                'per_page' => $perPage,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

}
