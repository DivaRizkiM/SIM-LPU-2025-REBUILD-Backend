<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSyncKelurahanJob;
use App\Models\Kelurahan;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ApiController; // tambahkan di use section
class KelurahanController extends Controller
{
    // public function index()
    // {
    //     try {
    //         // Ambil parameter offset, limit, search, dan order dari permintaan
    //         $offset = request()->get('offset', 0);
    //         $limit = request()->get('limit', 10);
    //         $search = request()->get('search', '');
    //         $getOrder = request()->get('order', '');
    //         $idProvinsi = request()->get('id_provinsi', '');
    //         $idKab = request()->get('id_kabupaten_kota', '');
    //         $idKec = request()->get('id_kecamatan', '');

    //         // Tentukan aturan urutan default dan pemetaan urutan
    //         $defaultOrder = $getOrder ? $getOrder : "kelurahan.id ASC";
    //         $orderMappings = [
    //             'idASC' => 'kelurahan.id ASC',
    //             'idDESC' => 'kelurahan.id DESC',
    //             'namakelurahanASC' => 'kelurahan.nama ASC',
    //             'namakelurahanDESC' => 'kelurahan.nama DESC',
    //             'namakecamatanASC' => 'kecamatan.nama ASC',
    //             'namakecamatanDESC' => 'kecamatan.nama DESC',
    //             'namakabupatenASC' => 'kabupaten_kota.nama ASC',
    //             'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
    //             'namaprovinsiASC' => 'provinsi.nama ASC',
    //             'namaprovinsiDESC' => 'provinsi.nama DESC',
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

    //         // // Query kelurahan dengan kondisi pencarian dan urutan yang ditentukan
    //         // $kelurahans = Kelurahan::leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
    //         //     ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
    //         //     ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id')
    //         //     ->select('kelurahan.*', 'kecamatan.nama as nama_kecamatan', 'kabupaten_kota.nama as nama_kabupaten', 'provinsi.nama as nama_provinsi')
    //         //     ->where(function ($query) use ($search) {
    //         //         $query->where('kelurahan.nama', 'like', "%$search%")
    //         //             ->orWhere('kecamatan.nama', 'like', "%$search%")
    //         //             ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
    //         //             ->orWhere('provinsi.nama', 'like', "%$search%");
    //         //     })
    //         //     ->orderByRaw($order)
    //         //     ->offset($offset)
    //         //     ->limit($limit)
    //         //     ->get();

    //         $kelurahansQuery = Kelurahan::leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
    //             ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
    //             ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id')
    //             ->select('kelurahan.*', 'kecamatan.nama as nama_kecamatan', 'kabupaten_kota.nama as nama_kabupaten', 'provinsi.nama as nama_provinsi')
    //             ->orderByRaw($order)
    //         ;
    //         $total_data = $kelurahansQuery->count();
    //         // Filter by id_provinsi if provided
    //         if ($idProvinsi) {
    //             $kelurahansQuery->where('kelurahan.id_provinsi', $idProvinsi);
    //         }
    //         if ($idKab) {
    //             $kelurahansQuery->where('kelurahan.id_kabupaten_kota', $idKab);
    //         }
    //         if ($idKec) {
    //             $kelurahansQuery->where('kelurahan.id_kecamatan', $idKec);
    //         }

    //         if ($search !== '') {
    //             $kelurahansQuery->where(function ($query) use ($search) {
    //                 $query->where('kelurahan.nama', 'like', "%$search%")
    //                     ->orWhere('kecamatan.nama', 'like', "%$search%")
    //                     ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
    //                     ->orWhere('provinsi.nama', 'like', "%$search%");
    //             });
    //         }

    //         $kelurahans = $kelurahansQuery->offset($offset)
    //             ->limit($limit)->get();

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data' => $total_data,
    //             'data' => $kelurahans,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function index(Request $request)
    {
        try {
            // Ambil parameter page, per-page, offset, limit, order, search, dan loopCount dari permintaan
            $page = $request->get('page', 1); // Default page to 1
            $perPage = $request->get('per-page', 10); // Default per-page to 10
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 10);
            $loopCount = $request->get('loopCount', 1);
            $idProvinsi = $request->get('id_provinsi', '');
            $idKab = $request->get('id_kabupaten_kota', '');
            $idKec = $request->get('id_kecamatan', '');

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = $getOrder ? $getOrder : "kelurahan.id ASC";
            $orderMappings = [
                'idASC' => 'kelurahan.id ASC',
                'idDESC' => 'kelurahan.id DESC',
                'namakelurahanASC' => 'kelurahan.nama ASC',
                'namakelurahanDESC' => 'kelurahan.nama DESC',
                'namakecamatanASC' => 'kecamatan.nama ASC',
                'namakecamatanDESC' => 'kecamatan.nama DESC',
                'namakabupatenASC' => 'kabupaten_kota.nama ASC',
                'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
                'namaprovinsiASC' => 'provinsi.nama ASC',
                'namaprovinsiDESC' => 'provinsi.nama DESC',
            ];

            // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            // Validasi aturan untuk parameter masukan
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'page' => 'integer|min:1',
                'per-page' => 'integer|min:1',
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
                'id_provinsi' => 'integer|exists:provinsi,id',
                'id_kabupaten_kota' => 'integer|exists:kabupaten_kota,id',
                'id_kecamatan' => 'integer|exists:kecamatan,id',
                'loopCount' => 'integer|min:1',
            ];

            $validator = Validator::make([
                'page' => $page,
                'per-page' => $perPage,
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'id_provinsi' => $idProvinsi,
                'id_kabupaten_kota' => $idKab,
                'id_kecamatan' => $idKec,
                'loopCount' => $loopCount,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Hitung offset dan limit jika offset dan limit tidak diberikan
            if (is_null($offset) || is_null($limit)) {
                $offset = ($page - 1) * $perPage * $loopCount;
                $limit = $perPage * $loopCount;
            }

            // Query kelurahan dengan kondisi pencarian dan filter yang ditentukan
            $kelurahansQuery = Kelurahan::leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id')
                ->select('kelurahan.*', 'kecamatan.nama as nama_kecamatan', 'kabupaten_kota.nama as nama_kabupaten', 'provinsi.nama as nama_provinsi');

            if ($idProvinsi) {
                $kelurahansQuery->where('kelurahan.id_provinsi', $idProvinsi);
            }
            if ($idKab) {
                $kelurahansQuery->where('kelurahan.id_kabupaten_kota', $idKab);
            }
            if ($idKec) {
                $kelurahansQuery->where('kelurahan.id_kecamatan', $idKec);
            }

            if ($search !== '') {
                $kelurahansQuery->where(function ($query) use ($search) {
                    $query->where('kelurahan.nama', 'like', "%$search%")
                          ->orWhere('kecamatan.nama', 'like', "%$search%")
                          ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                          ->orWhere('provinsi.nama', 'like', "%$search%");
                });
            }

            $total_data = $kelurahansQuery->count();
            $kelurahans = $kelurahansQuery->orderByRaw($order)
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
                'data' => $kelurahans,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    public function show($id)
    {
        try {
            $kelurahan = Kelurahan::leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id')
                ->select('kelurahan.*', 'kecamatan.nama as nama_kecamatan', 'kabupaten_kota.nama as nama_kabupaten', 'provinsi.nama as nama_provinsi')
                ->where('id', $id)->first();

            return response()->json(['status' => 'SUCCESS', 'data' => $kelurahan]);
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
                'id_kecamatan' => 'required|exists:kecamatan,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $kelurahan = Kelurahan::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Kelurahan',
                'modul' => 'Kelurahan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kelurahan], 201);
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
                'id_kecamatan' => 'required|exists:kecamatan,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $kelurahan = Kelurahan::where('id', $id)->first();
            $kelurahan->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Kelurahan',
                'modul' => 'Kelurahan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kelurahan]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $kelurahan = Kelurahan::where('id', $id)->first();
            $kelurahan->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Kelurahan',
                'modul' => 'Kelurahan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kelurahan deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function syncKelurahan(Request $request)
    {
        try {
            $endpoint = 'kelurahan';
            $userAgent = $request->header('User-Agent');

            // buat satu request awal untuk mengetahui total_data (gunakan perPage yang wajar)
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
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Sinkronisasi Kelurahan',
                'modul' => 'Kelurahan',
                'id_user' => Auth::user(),
            ];
            UserLog::create($userLog);

            // Dispatch job per halaman — gunakan queue worker untuk memproses paralel sesuai konfigurasi queue
            for ($p = 1; $p <= $pages; $p++) {
                ProcessSyncKelurahanJob::dispatch($endpoint, $userAgent, $p, $perPage);
            }

            return response()->json([
                'status' => 'IN_PROGRESS',
                'message' => 'Sinkronisasi sedang diproses (jobs dispatched)',
                'total_records' => $total,
                'pages' => $pages,
                'per_page' => $perPage,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

}
