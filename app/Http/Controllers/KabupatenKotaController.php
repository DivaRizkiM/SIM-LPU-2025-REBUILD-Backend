<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\UserLog;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\ApiRequestLog;
use App\Models\KabupatenKota;
use Illuminate\Support\Facades\DB;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class KabupatenKotaController extends Controller
{
    // public function index()
    // {
    //     try {
    //         // Ambil parameter offset, limit, order, dan search dari permintaan
    //         $offset = request()->get('offset', 0);
    //         $limit = request()->get('limit', 10);
    //         $search = request()->get('search', '');
    //         $getOrder = request()->get('order', '');
    //         $idProvinsi = request()->get('id_provinsi', '');

    //         // Tentukan aturan urutan default dan pemetaan urutan
    //         $defaultOrder = $getOrder ? $getOrder : "kabupaten_kota.id ASC";
    //         $orderMappings = [
    //             'idASC' => 'kabupaten_kota.id ASC',
    //             'idDESC' => 'kabupaten_kota.id DESC',
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
    //             'id_provinsi' => 'integer|exists:provinsi,id', // Create validation rule for id_provinsi
    //         ];

    //         $validator = Validator::make([
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'id_provinsi' => $idProvinsi,
    //         ], $rules);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'ERROR',
    //                 'message' => 'Invalid input parameters',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         // Query kabupaten/kota with search condition if search keyword is provided
    //         $kabupatenKotasQuery = KabupatenKota::leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
    //             ->select('kabupaten_kota.*', 'provinsi.nama as nama_provinsi')
    //             ->orderByRaw($order);
    //         $total_data = $kabupatenKotasQuery->count();
    //         // Filter by id_provinsi if provided
    //         if ($idProvinsi) {
    //             $kabupatenKotasQuery->where('kabupaten_kota.id_provinsi', $idProvinsi);
    //         }

    //         if ($search !== '') {
    //             $kabupatenKotasQuery->where(function ($query) use ($search) {
    //                 $query->where('kabupaten_kota.nama', 'like', "%$search%")
    //                     ->orWhere('provinsi.nama', 'like', "%$search%");
    //             });
    //         }

    //         $kabupatenKotas = $kabupatenKotasQuery->offset($offset)
    //             ->limit($limit)->get();

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data' => $total_data,
    //             'data' => $kabupatenKotas,
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
            $defaultOrder = $getOrder ? $getOrder : "kabupaten_kota.id ASC";
            $orderMappings = [
                'idASC' => 'kabupaten_kota.id ASC',
                'idDESC' => 'kabupaten_kota.id DESC',
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
                'id_provinsi' => 'integer|exists:provinsi,id|nullable',
               'loopCount' => 'integer|min:1|nullable',
            ];


            $validator = Validator::make([
                'page' => $page,
                'per-page' => $perPage,
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'id_provinsi' => $idProvinsi,
                'loopCount' => $loopCount,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Query data kabupaten/kota dengan filter dan pencarian
            $query = KabupatenKota::leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->select('kabupaten_kota.*', 'provinsi.nama as nama_provinsi');

            if ($idProvinsi) {
                $query->where('kabupaten_kota.id_provinsi', $idProvinsi);
            }

            if ($search !== '') {
                $query->where(function ($query) use ($search) {
                    $query->where('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                });
            }

            $total_data = $query->count();
            $kabupatenKotas = $query->orderByRaw($order)
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
                'data' => $kabupatenKotas,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function show($id)
    {
        try {
            $kabupatenKota = KabupatenKota::leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->select('kabupaten_kota.*', 'provinsi.nama as nama_provinsi')
                ->where('kabupaten_kota.id', $id)->first();

            return response()->json(['status' => 'SUCCESS', 'data' => $kabupatenKota]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'required|exists:provinsi,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $kabupatenKota = KabupatenKota::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Kabupaten Kota',
                'modul' => 'Kabupaten Kota',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kabupatenKota], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'required|exists:provinsi,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $kabupatenKota = KabupatenKota::where('id', $id)->first();
            $kabupatenKota->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Kabupaten Kota',
                'modul' => 'Kabupaten Kota',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $kabupatenKota]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $kabupatenKota = KabupatenKota::where('id', $id)->first();
            $kabupatenKota->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Kabupaten Kota',
                'modul' => 'Kabupaten Kota',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kabupaten/Kota deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function syncKabupaten(Request $request)
    {
        try {
            // Memulai transaksi database untuk meningkatkan kinerja

            // Mendefinisikan endpoint untuk sinkronisasi kabupaten/kota
            $endpoint = 'kota_kab';
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $all_get_data = [];

            $totalTarget = 0;
            $totalSumber = 0;

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint kabupaten/kota
            $response = $apiController->makeRequest($request);

            // Mengambil data kabupaten/kota dari respons
            $dataKabupatenKota = $response['data'] ?? [];
            DB::beginTransaction();
            $successful = 0;
            $available = 0;
            $available = count($dataKabupatenKota);

            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Kabupaten/Kota',
                'tanggal' => now(),
                'ip_address' => $serverIpAddress,
                'platform_request' => $platform_request,
                'successful_records' => 0,
                'available_records' => $response['total_data'] ?? $available,
                'total_records' => 0,
                'status' => 'Memuat Data',
            ]);

            $payload = ApiRequestPayloadLog::create([
                'api_request_log_id' => $apiRequestLog->id,
                'payload' => null,
            ]);
            // Memproses setiap data kabupaten/kota dari respons
            foreach ($dataKabupatenKota as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $all_get_data[] = $data;
                    $totalTarget++;
                }
                // Mencari kabupaten/kota berdasarkan ID
                $kabupatenKota = KabupatenKota::find($data['kode_kota_kab']);

                // Jika kabupaten/kota ditemukan, perbarui data
                if ($kabupatenKota) {
                    $kabupatenKota->update([
                        'nama' => $data['nama_kota_kab'],
                        'id_provinsi' => $data['kode_provinsi'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika kabupaten/kota tidak ditemukan, tambahkan data baru
                    KabupatenKota::create([
                        'id' => $data['kode_kota_kab'],
                        'nama' => $data['nama_kota_kab'],
                        'id_provinsi' => $data['kode_provinsi'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
                $totalSumber++;
                $successful++;
            }
            $updated_keterangan = json_encode($all_get_data);
            $apiLogData = [
                'komponen' => 'Profile Regional',
                'tanggal' => now(),
                'ip_addres' => $serverIpAddress,
                'platform_request' => $platform_request,
                'error_code' => null,
                'ssid' => null,
                'identifier_activity' => 'request',
                'sumber' => $totalSumber,
                'target' => $totalTarget,
                'keterangan' => $updated_keterangan,
                'status' => 'success',
            ];

            $createApiLog = ApiLog::create($apiLogData);
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Sinkronisasi Kabupaten Kota',
                'modul' => 'Kabupaten Kota',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            $status = ($successful == $available) ? 'success' : 'on progress';
            $updated_payload = $payload->payload ?? '';
            $jsonData = json_encode($data);
            $fileSize = strlen($jsonData);
            $data['size'] = $fileSize;

            if ($updated_payload !== '' || $payload->payload !== null) {
                $existing_payload = json_decode($updated_payload, true);
                $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];
                $new_payload = (object) $data;
                $existing_payload[] = $new_payload;
                $updated_payload = json_encode($existing_payload);
            } else {
                $updated_payload = json_encode([(object) $data]);
            }

            sleep(2);
            $payload->update([
                'payload' => $updated_payload,
            ]);

            $apiRequestLog->update([
                'successful_records' => $successful,
                'status' => $status,
            ]);
            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi kabupaten/kota berhasil',
            ], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

}
