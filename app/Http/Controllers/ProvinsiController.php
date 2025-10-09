<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\UserLog;
use App\Models\Provinsi;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\ApiRequestLog;
use Illuminate\Support\Facades\DB;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProvinsiController extends Controller
{
    // public function index()
    // {
    //     try {
    //         $offset = request()->get('offset', 0);
    //         $limit = request()->get('limit', 10);
    //         $search = request()->get('search', '');
    //         $getOrder = request()->get('order', '');
    //         $defaultOrder = $getOrder ? $getOrder : "id ASC";
    //         $orderMappings = [
    //             'idASC' => 'id ASC',
    //             'idDESC' => 'id DESC',
    //             'namaASC' => 'nama ASC',
    //             'namaDESC' => 'nama DESC',
    //         ];

    //         // Set the order based on the mapping or use the default order if not found
    //         $order = $orderMappings[$getOrder] ?? $defaultOrder;
    //         // Validation rules for input parameters
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
    //         $provinsisQuery = Provinsi::orderByRaw($order);
    //         $total_data = $provinsisQuery->count();
    //         if ($search !== '') {
    //             $provinsisQuery->where('nama', 'like', "%$search%");
    //         }
    //         $provinsis = $provinsisQuery->offset($offset)
    //             ->limit($limit)->get();

    //         return response()->json([
    //             'status' => 'SUCCESS',
    //             'offset' => $offset,
    //             'limit' => $limit,
    //             'order' => $getOrder,
    //             'search' => $search,
    //             'total_data' => $total_data,
    //             'data' => $provinsis,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function index(Request $request)
    {
        try {
            // Ambil parameter dari permintaan
            $page = $request->get('page',null);
            $perPage = $request->get('per-page',null);
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
            $defaultOrder = $getOrder ? $getOrder : "id ASC";
            $orderMappings = [
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'namaASC' => 'nama ASC',
                'namaDESC' => 'nama DESC',
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

            // Query data provinsi dengan offset, limit, dan pencarian
            $query = Provinsi::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }

            $provinsisQuery = $query->orderByRaw($order);

            $total_data = $query->count();
            $provinsis = $query->offset($offset)
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
                'data' => $provinsis,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $provinsi = Provinsi::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $provinsi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $provinsi = Provinsi::create($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Create Provinsi',
                'modul' => 'Provinsi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $provinsi], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $provinsi = Provinsi::where('id', $id)->first();
            $provinsi->update($request->all());
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Update Provinsi',
                'modul' => 'Provinsi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'data' => $provinsi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $provinsi = Provinsi::where('id', $id)->first();
            $provinsi->delete();
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Delete Provinsi',
                'modul' => 'Provinsi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Provinsi deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    public function syncProvinsi(Request $request)
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'provinsi';
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

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataProvinsi = $response['data'] ?? [];
            if (!$dataProvinsi) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();
            $successful = 0;
            $available = 0;
            $available = count($dataProvinsi);

            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Provinsi',
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

            // Memproses setiap data provinsi dari respons
            foreach ($dataProvinsi as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $all_get_data[] = $data;
                    $totalTarget++;
                }
                // Mencari provinsi berdasarkan ID
                $provinsi = Provinsi::find($data['kode_provinsi']);

                // Jika provinsi ditemukan, perbarui data
                if ($provinsi) {
                    $provinsi->update([
                        'nama' => $data['nama_provinsi'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    Provinsi::create([
                        'id' => $data['kode_provinsi'],
                        'nama' => $data['nama_provinsi'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
                $successful++;
                $totalSumber++;
                
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

                $payload->update([
                    'payload' => $updated_payload,
                ]);

                $apiRequestLog->update([
                    'successful_records' => $successful,
                    'status' => $status,
                ]);
            }
            $updated_keterangan = json_encode($all_get_data);
            $apiLogData = [
                'komponen' => 'Provinsi',
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
                'aktifitas' =>'Sinkronisasi Provinsi',
                'modul' => 'Provinsi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi provinsi berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}
