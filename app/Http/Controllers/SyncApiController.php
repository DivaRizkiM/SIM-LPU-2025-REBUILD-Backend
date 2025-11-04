<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSyncAtribusiJob;
use App\Jobs\ProcessSyncBiayaJob;
use App\Jobs\ProcessSyncBiayaPrognosaJob;
use App\Jobs\ProcessSyncKCUJob;
use App\Jobs\ProcessSyncKPCJob;
use App\Jobs\ProcessSyncMitraLpuJob;
use App\Jobs\ProcessSyncPendapatanJob;
use App\Jobs\ProcessSyncPetugasKCPJob;
use App\Jobs\ProcessSyncProduksiJob;
use App\Jobs\ProcessSyncProduksiPrognosaJob;
use App\Jobs\RsyncJob;
use App\Models\ApiLog;
use App\Models\BiayaAtribusiDetail;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\JenisBisnis;
use App\Models\KategoriBiaya;
use App\Models\Kpc;
use App\Models\Kprk;
use App\Models\Npp;
use App\Models\ProduksiNasional;
use App\Models\Regional;
use App\Models\RekeningBiaya;
use App\Models\RekeningProduksi;
use App\Models\DashboardProduksiPendapatan;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\KategoriPendapatan;
use App\Models\LayananJasaKeuangan;
use App\Models\LayananKurir;
use App\Models\VerifikasiLtk;

class SyncApiController extends Controller
{


    public function auth()
    {
        // Cek jika pengguna terautentikasi
        if (Auth::check()) {
            // Mendapatkan ID pengguna
            return Auth::id(); // Atau return Auth::user()->id;
        }

        // Jika pengguna tidak terautentikasi, kembalikan null atau nilai yang sesuai
        return null;
    }
    public function maxAttempts()
    {
        return 5; // Set the maximum attempts
    }

    public function backoff()
    {
        return 10; // Set the delay in seconds before retrying the job
    }
    public function timeout()
    {
        return 0;
    }
    public function syncRegional(Request $request)
    {
        try {

            $endpoint = 'profil_regional';

            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            // $request = new Request();
            $url_request = $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($url_request);
            // dd($response);

            $dataRegional = $response['data'] ?? [];
            if (!$dataRegional) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $available = count($dataRegional);

            DB::beginTransaction();
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Regional',
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
                'payload' => null, // Store the payload as JSON
            ]);

            foreach ($dataRegional as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }


                $regional = Regional::find($data['id_regional']);

                if ($regional) {
                    $regional->update([
                        'nama' => $data['nama_regional'],

                    ]);
                    $successful++;
                } else {

                    Regional::create([
                        'id' => $data['id_regional'],
                        'kode' => $data['kode_regional'],
                        'nama' => $data['nama_regional'],

                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
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
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Regional',
                'modul' => 'regional',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi regional berhasil'
            ], 200);
        } catch (\Exception $e) {



            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKategoriBiaya(Request $request)
    {
        try {

            $endpoint = 'kategori_biaya';
            $serverIpAddress = gethostbyname(gethostname());
            $userAgent = $request->header('User-Agent');
            $agent = new Agent();
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;
            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            $dataKategoriBiaya = $response['data'] ?? [];
            if (!$dataKategoriBiaya) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $available = count($dataKategoriBiaya);
            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Kategori Biaya',
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
                'payload' => null, // Store the payload as JSON
            ]);
            foreach ($dataKategoriBiaya as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }
                $kategoriBiaya = KategoriBiaya::find($data['id']);

                if ($kategoriBiaya) {
                    $kategoriBiaya->update([
                        'nama' => $data['deskripsi'],

                    ]);
                    $successful++;
                } else {

                    KategoriBiaya::create([
                        'id' => $data['id'],
                        'nama' => $data['deskripsi'],

                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
                    $updated_payload = json_encode($existing_payload);
                } else {
                    $updated_payload = json_encode([(object) $data]);
                }

                $payload->update([
                    'payload' => $updated_payload,
                ]);

                $apiRequestLog->update([

                    'successful_records' => $successful,
                    'status' => $status
                ]);
            }
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Kategori Biaya',
                'modul' => 'kategori biaya',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi kategori biayar berhasil'
            ], 200);
        } catch (\Exception $e) {



            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncRekeningBiaya(Request $request)
    {
        try {

            $endpoint = 'rekening_biaya';
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;
            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            $dataRekeningBiaya = $response['data'] ?? [];
            if (!$dataRekeningBiaya) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $available = count($dataRekeningBiaya);
            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Rekening Biaya',
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
                'payload' => null, // Store the payload as JSON
            ]);
            foreach ($dataRekeningBiaya as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }
                $rekeningBiaya = RekeningBiaya::find($data['id_rekening']);

                if ($rekeningBiaya) {
                    $rekeningBiaya->update([
                        'nama' => $data['nama_rekening'],
                        'kode_rekening' => $data['kode_rekening'],
                        'tgl_sinkronisasi' => now(),

                    ]);
                    $successful++;
                } else {

                    RekeningBiaya::create([
                        'id' => $data['id_rekening'],
                        'kode_rekening' => $data['kode_rekening'],
                        'nama' => $data['nama_rekening'],
                        'tgl_sinkronisasi' => now(),

                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
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
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Rekening Biaya',
                'modul' => 'rekening biaya',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi rekening biaya berhasil'
            ], 200);
        } catch (\Exception $e) {



            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncRekeningProduksi(Request $request)
    {
        try {

            $endpoint = 'rekening_produksi';
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            $dataRekeningProduksi = $response['data'] ?? [];
            if (!$dataRekeningProduksi) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            // dd($dataRekeningProduksi);
            $available = count($dataRekeningProduksi);
            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Rekening Produksi',
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
                'payload' => null, // Store the payload as JSON
            ]);

            foreach ($dataRekeningProduksi as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }
                $rekeningProduksi = RekeningProduksi::where('kode_rekening', $data['kode_rekening'])->first();

                if ($rekeningProduksi) {
                    $rekeningProduksi->update([

                        'nama' => $data['nama_rekening'],
                        'id_produk' => $data['id_produk'],
                        'nama_produk' => $data['nama_produk'],
                        'id_tipe_bisnis' => $data['id_tipe_bisnis'],

                    ]);
                    $successful++;
                } else {

                    RekeningProduksi::create([
                        'id' => $data['id_rekening'],
                        'kode_rekening' => $data['kode_rekening'],
                        'nama' => $data['nama_rekening'],
                        'id_produk' => $data['id_produk'],
                        'nama_produk' => $data['nama_produk'],
                        'id_tipe_bisnis' => $data['id_tipe_bisnis'],
                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
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
            }
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Rekening Produksi',
                'modul' => 'rekening produksi',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi rekening produksi berhasil'
            ], 200);
        } catch (\Exception $e) {



            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncTipeBisnis(Request $request)
    {
        try {

            $endpoint = 'tipe_bisnis';
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);
            // dd($response);

            $dataTipeBisnis = $response['data'] ?? [];
            if (!$dataTipeBisnis) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $available = count($dataTipeBisnis);
            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Tipe Bisnis',
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
                'payload' => null, // Store the payload as JSON
            ]);

            foreach ($dataTipeBisnis as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }

                $rekeningBiaya = JenisBisnis::find($data['id']);

                if ($rekeningBiaya) {
                    $rekeningBiaya->update([
                        'nama' => $data['deskripsi'],

                    ]);
                    $successful++;
                } else {

                    JenisBisnis::create([
                        'id' => $data['id'],
                        'nama' => $data['deskripsi'],

                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
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
            }
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Tipe Bisnis',
                'modul' => 'tipe bisnis',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi tipe bisnis berhasil'
            ], 200);
        } catch (\Exception $e) {



            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncPetugasKCP(Request $request)
    {
        try {
            $endpoint = 'daftar_kpc';
            $endpointPetugas = 'petugas_kpc';
            $idKpc = $request->id_kpc;
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Petugas KCP',
                'modul' => 'petugas KCP',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);
            $job = ProcessSyncPetugasKCPJob::dispatch($endpoint, $endpointPetugas, $userAgent);

            return response()->json([
                'status' => 'IN_PROGRESS',
                'message' => 'Sinkronisasi sedang di proses',
            ], 200);
        } catch (\Exception $e) {

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKCU(Request $request)
    {
        try {
            $endpoint = 'daftar_kprk';
            // dd($endpoint);
            $endpointProfile = 'profil_kprk';
            $idKprk = $request->id_kprk;
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi KCU',
                'modul' => 'KCU',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);
            $job = ProcessSyncKCUJob::dispatch($endpoint, $endpointProfile, $userAgent);

            return response()->json([
                'status' => 'IN_PROGRESS',
                'message' => 'Sinkronisasi sedang di proses',
            ], 200);
        } catch (\Exception $e) {

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKPC(Request $request)
    {
        try {
            $endpoint = 'daftar_kpc';
            $endpointProfile = 'profil_kpc';
            $idKpc = $request->id_kpc;
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi KPC',
                'modul' => 'KPC',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);
            $job = ProcessSyncKPCJob::dispatch($endpoint, $endpointProfile, $userAgent);

            return response()->json([
                'status' => 'IN_PROGRESS',
                'message' => 'Sinkronisasi sedang di proses',
            ], 200);
        } catch (\Exception $e) {

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncBiayaAtribusi(Request $request)
    {
        try {

            $endpoint = '';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? null;
            $kategori_biaya = $request->kategori_biaya;
            $bulan = $request->bulan;
            $tahun = $request->tahun;
            $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();

            if ($kategori_biaya == 1) {
                $endpoint = 'biaya_upl';
            } elseif ($kategori_biaya == 2) {
                $endpoint = 'biaya_angkutan_pos_setempat';
            } elseif ($kategori_biaya == 3) {
                $endpoint = 'biaya_sopir_tersier';
            } else {
                $endpoint = null;
            }

            $list = '';
            if ($id_kprk == null) {
                $list = Kprk::where('id_regional', $id_regional)->get();
            } else {
                $list = Kprk::where('id', $id_kprk)->get();
            }

            if (!$list || $list->isEmpty()) {
                return response()->json(['error' => 'kprk not found'], 404);
            } else {
                // dd($list);
                $totalItems = $list->count();
                $userLog = [
                    'timestamp' => now(),
                    'aktifitas' => 'Sinkronisasi Biaya Atribusi',
                    'modul' => 'biaya atribusi',
                    'id_user' => $this->auth(),
                ];

                $userLog = UserLog::create($userLog);
                $job = ProcessSyncAtribusiJob::dispatch($list, $totalItems, $id_regional, $id_kprk, $bulan, $endpoint, $tahun, $userAgent);

                return response()->json([
                    'status' => 'IN_PROGRESS',
                    'message' => 'Sinkronisasi sedang di proses',
                ], 200);
            }
        } catch (\Exception $e) {



            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncBiaya(Request $request)
    {
        try {
            $endpoint = 'biaya_bulanan';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $bulan = $request->bulan;
            $tahun = $request->tahun;
            $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);

            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            // dd($platform);
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'exists:kprk,id',
                'id_kpc' => 'exists:kpc,id',
                'tahun' => 'required',
                'bulan' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $list = '';
            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('nomor_dirian', $id_kpc)->get();
            }
            if (!$list || $list->isEmpty()) {
                return response()->json(['error' => 'kpc not found'], 404);
            } else {
                $totalItems = $list->count();


                // Dispatch job sebelum pernyataan return
                $userLog = [
                    'timestamp' => now(),
                    'aktifitas' => 'Sinkronisasi Biaya Rutin',
                    'modul' => 'biaya rutin',
                    'id_user' => $this->auth(),
                ];

                $userLog = UserLog::create($userLog);
                $job = ProcessSyncBiayaJob::dispatch($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $bulan, $tahun, $userAgent);

                return response()->json([
                    'status' => 'IN_PROGRESS',
                    'message' => 'Sinkronisasi sedang di proses',
                ], 200);
            }
        } catch (\Exception $e) {
            // Tangani pengecualian di sini jika diperlukan
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncBiayaPrognosa(Request $request)
    {
        try {
            $endpoint = 'biaya_prognosa';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;
            // $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            // dd($platform);
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'exists:kprk,id',
                'id_kpc' => 'exists:kpc,id',
                'tahun' => 'required',
                'triwulan' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }

            $list = '';
            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('id', $id_kpc)->get();
            }
            if (!$list || $list->isEmpty()) {
                return response()->json(['error' => 'kpc not found'], 404);
            } else {
                $totalItems = $list->count();
                $userLog = [
                    'timestamp' => now(),
                    'aktifitas' => 'Sinkronisasi Biaya Rutin Prognosa',
                    'modul' => 'biaya rutin prognosa',
                    'id_user' => $this->auth(),
                ];

                $userLog = UserLog::create($userLog);
                // Dispatch job sebelum pernyataan return
                $job = ProcessSyncBiayaPrognosaJob::dispatch($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $triwulan, $tahun, $userAgent);

                return response()->json([
                    'status' => 'IN_PROGRESS',
                    'message' => 'Sinkronisasi sedang di proses',
                ], 200);
            }
        } catch (\Exception $e) {
            // Tangani pengecualian di sini jika diperlukan
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncProduksi(Request $request)
    {
        try {

            $endpoint = 'produksi_bulanan';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $bulan = $request->bulan;
            $tahun = $request->tahun;
            $tipe_bisnis = $request->tipe_bisnis ?? '';
            $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $triwulan = ceil($bulan / 3);

            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'exists:kprk,id',
                'id_kpc' => 'exists:kpc,nomor_dirian',
                'tahun' => 'required',
                'bulan' => 'required',
                'tipe_bisnis' => 'exists:jenis_layanan,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }
            $list = [];

            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('nomor_dirian', $id_kpc)->get();
            }
            // dd($list);
            if (!$list || $list->isEmpty()) {
                return response()->json(['error' => 'kpc not found'], 404);
            } else {
                $totalItems = $list->count();
                // dd($list);
                $userLog = [
                    'timestamp' => now(),
                    'aktifitas' => 'Sinkronisasi Produksi',
                    'modul' => 'biaya produksi',
                    'id_user' => $this->auth(),
                ];


                $userLog = UserLog::create($userLog);
                // Dispatch job sebelum pernyataan return
                $job = ProcessSyncProduksiJob::dispatch($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $bulan, $tahun, $userAgent, $tipe_bisnis);

                return response()->json([
                    'status' => 'IN_PROGRESS',
                    'message' => 'Sinkronisasi sedang di proses',
                ], 200);
            }
        } catch (\Exception $e) {
            dd($e);
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncProduksiPrognosa(Request $request)
    {
        try {

            $endpoint = 'produksi_prognosa';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;
            $tipe_bisnis = $request->tipe_bisnis ?? '';

            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();

            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'exists:kprk,id',
                'id_kpc' => 'exists:kpc,nomor_dirian',
                'tahun' => 'required',
                'triwulan' => 'required',
                'tipe_bisnis' => 'exists:jenis_layanan,id',
            ]);


            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }
            $list = [];

            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('nomor_dirian', $id_kpc)->get();
            }
            if (!$list || $list->isEmpty()) {
                return response()->json(['error' => 'kpc not found'], 404);
            } else {

                $totalItems = $list->count();
                $userLog = [
                    'timestamp' => now(),
                    'aktifitas' => 'Sinkronisasi Produksi Prognosa',
                    'modul' => 'biaya produksi prognosa',
                    'id_user' => $this->auth(),
                ];

                $userLog = UserLog::create($userLog);
                // Dispatch job sebelum pernyataan return
                $job = ProcessSyncProduksiPrognosaJob::dispatch($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $triwulan, $tahun, $userAgent, $tipe_bisnis);

                return response()->json([
                    'status' => 'IN_PROGRESS',
                    'message' => 'Sinkronisasi sedang di proses',
                ], 200);
            }
        } catch (\Exception $e) {


            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncNpp(Request $request)
    {
        try {

            $endpoint = 'biaya_nasional';
            $tahun = $request->tahun;
            $bulan = $request->bulan;
            $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;

            // dd($createApiLog);

            // $platform_request = $platform . '/' . $browser;
            $apiController = new ApiController();
            $url_request = $endpoint . '?tahunbulan=' . $tahun . $bulan;
            $request->merge(['end_point' => $url_request]);
            $response = $apiController->makeRequest($request);
            $access_token = $response['access_token'] ?? null;
            $dataNpp = $response['data'] ?? [];
            if (!$dataNpp) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $available = count($dataNpp);
            DB::beginTransaction();
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Biaya Nasional',
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
                'payload' => null, // Store the payload as JSON
            ]);
            // dd($createApiLog);
            foreach ($dataNpp as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }

                $npp = Npp::find($tahun . $bulan . $data['koderekening']);

                if ($npp) {
                    $npp->update([
                        'bsu' => $data['bsu'],
                        'nama_file' => $data['linkfile'],
                        'id_status' => 7,

                    ]);
                    $data['size'] = 0;

                    if (!empty($data['linkfile'])) {
                        $namaFile = $data['linkfile'];
                        $destinationPath = storage_path('/app/public/lampiran');
                        $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --delete rsync://kominfo2@103.123.39.227:/lpu/{$namaFile} {$destinationPath} 2>&1";
                        $output = shell_exec($rsyncCommand);
                        if (preg_match('/total size is (\d+)/', $output, $matches)) {
                            $size = (int)$matches[1];
                        }
                        $data['size'] = $size; // Menyimpan ukuran file
                    }
                    $successful++;
                } else {
                    $tahunbulan = $data['bulantahun'];
                    $tahun = substr($tahunbulan, 0, 4);
                    $bulan = substr($tahunbulan, 4, 2);
                    Npp::create([
                        'id' => $tahun . $bulan . $data['koderekening'],
                        'id_rekening_biaya' => $data['koderekening'],
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'bsu' => $data['bsu'],
                        'nama_file' => $data['linkfile'],
                        'id_status' => 7,
                    ]);
                    $data['size'] = 0;

                    if (!empty($data['linkfile'])) {
                        $namaFile = $data['linkfile'];
                        $destinationPath = storage_path('/app/public/lampiran');
                        $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --delete rsync://kominfo2@103.123.39.227:/lpu/{$namaFile} {$destinationPath} 2>&1";
                        $output = shell_exec($rsyncCommand);
                        if (preg_match('/total size is (\d+)/', $output, $matches)) {
                            $size = (int)$matches[1];
                        }
                        $data['size'] = $size; // Menyimpan ukuran file
                    }
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
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
            }
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi NPP Nasional',
                'modul' => 'npp nasional',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);

            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi NPP Nasional berhasil'
            ], 200);
        } catch (\Exception $e) {



            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncDashboardProduksiPendapatan(Request $request)
    {
        try {
            $endpoint = 'dashboard_produksi_pendapatan';
            $tahun = $request->tahun;
            $bulan = $request->bulan;
            $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;

            $apiController = new ApiController();
            $url_request = $endpoint . '?tahunbulan=' . $tahun . $bulan;
            $request->merge(['end_point' => $url_request]);
            $response = $apiController->makeRequest($request);
            $access_token = $response['access_token'] ?? null;
            $dataProduksiPendapatan = $response['data'] ?? [];

            if (!$dataProduksiPendapatan) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $available = count($dataProduksiPendapatan);

            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Dashboard Produksi Pendapatan',
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

            // dd($dataProduksiPendapatan);d
            foreach ($dataProduksiPendapatan as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }


                $record = DashboardProduksiPendapatan::where([
                    'group_produk' => $data['GROUP_PRODUK'],
                    'bisnis' => $data['BISNIS'],
                    'status' => $data['STATUS'],
                    'tanggal' => (int)$tahun . (int)$bulan,
                ])->first();

                if ($record) {
                    $record->update([
                        'group_produk' => $data['GROUP_PRODUK'],
                        'bisnis' => $data['BISNIS'],
                        'status' => $data['STATUS'],
                        'jml_produksi' => (float)$data['JML_PRODUKSI'],
                        'jml_pendapatan' => (float)$data['JML_PENDAPATAN'],
                        'koefisien' => (float)$data['KOEFISIEN'],
                        'transfer_pricing' => (int)$data['transfer_pricing'],
                    ]);
                    $successful++;
                } else {
                    DashboardProduksiPendapatan::create([
                        'group_produk' => $data['GROUP_PRODUK'],
                        'bisnis' => $data['BISNIS'],
                        'status' => $data['STATUS'],
                        'tanggal' => (int)$tahun . (int)$bulan,
                        'jml_produksi' => (float)$data['JML_PRODUKSI'],
                        'jml_pendapatan' => (float)$data['JML_PENDAPATAN'],
                        'koefisien' => (float)$data['KOEFISIEN'],
                        'transfer_pricing' => (int)$data['transfer_pricing'],
                    ]);
                    $successful++;
                }
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
            }

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Dashboard Produksi Pendapatan',
                'modul' => 'dashboard produksi pendapatan',
                'id_user' => $this->auth(),
            ];

            UserLog::create($userLog);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi Dashboard Produksi Pendapatan berhasil',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncProduksiNasional(Request $request)
    {
        try {

            $endpoint = 'produksi_nasional';
            $tahun = $request->tahun;
            $bulan = $request->bulan;
            $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;
            $ssid = null;
            $apiController = new ApiController();
            $url_request = $endpoint . '?tahunbulan=' . $tahun . $bulan;
            $request->merge(['end_point' => $url_request]);
            $response = $apiController->makeRequest($request);
            $ssid = $response['access_token'] ?? null;
            $dataProduksiNasional = $response['data'] ?? [];
            if (!$dataProduksiNasional) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $available = count($dataProduksiNasional);
            DB::beginTransaction();
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Produksi Nasional',
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
                'payload' => null, // Store the payload as JSON
            ]);
            foreach ($dataProduksiNasional as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }
                $produksiNasional = ProduksiNasional::find($bulan . $tahun . $data['jml_produksi']);
                if ($produksiNasional) {
                    $produksiNasional->update([
                        'jml_pendapatan' => $data['Jml_Pendapatan'],
                        'status' => $data['status'],
                        'produk' => $data['produk'],
                    ]);
                    $successful++;
                } else {
                    $tahunbulan = $data['tanggal'];
                    $tahun = substr($tahunbulan, 0, 4);
                    $bulan = substr($tahunbulan, 4, 2);
                    ProduksiNasional::create([
                        'id' => $bulan . $tahun . $data['jml_produksi'],
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'jml_produksi' => $data['jml_produksi'],
                        'jml_pendapatan' => $data['Jml_Pendapatan'],
                        'status' => $data['status'],
                        'produk' => $data['produk'],
                        'bisnis' => $data['Bisnis'],
                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
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
            }
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Produksi Nasional',
                'modul' => 'produksi nasional',
                'id_user' => $this->auth(),
            ];
            $userLog = UserLog::create($userLog);
            DB::commit();
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi Produksi Nasional berhasil'
            ], 200);
        } catch (\Exception $e) {



            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncLampiran(Request $request)
    {
        try {
            // Validasi parameter input
            $validatedData = $request->validate([
                'tahun' => 'nullable|integer',
                'bulan' => 'nullable|integer|min:1|max:12',
            ]);

            $tahun = $validatedData['tahun'] ?? null;
            $bulan = str_pad($validatedData['bulan'] ?? null, 2, '0', STR_PAD_LEFT);
            $endpoint = 'lampiran_biaya';

            $userAgent = $request->header('User-Agent');
            $agent = new Agent();
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();

            $query = VerifikasiBiayaRutinDetail::leftJoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
                ->where('verifikasi_biaya_rutin_detail.lampiran', 'Y')
                ->select('verifikasi_biaya_rutin_detail.id');

            // Tambahkan kondisi berdasarkan tahun jika ada
            if (!empty($tahun)) {
                $query->where('verifikasi_biaya_rutin.tahun', $tahun);
            }

            if (!empty($bulan)) {
                $query->where('verifikasi_biaya_rutin_detail.bulan', $bulan);
            }

            $list = $query->get();
            $totalData = $list->count();

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Lampiran Biaya',
                'modul' => 'Lampiran',
                'id_user' => auth()->id(),
            ];

            UserLog::create($userLog);
            RsyncJob::dispatch($list, $endpoint, $totalData, $userAgent);

            // Mengembalikan respons JSON
            return response()->json([
                'status' => 'IN_PROGRESS',
                'message' => 'Sinkronisasi sedang diproses',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKategoriPendapatan(Request $request)
    {
        try {

            $endpoint = 'kategori_pendapatan';
            $serverIpAddress = gethostbyname(gethostname());
            $userAgent = $request->header('User-Agent');
            $agent = new Agent();
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;
            // Membuat instance dari ApiController
            $apiController = new ApiController();

            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            $response = $apiController->makeRequest($request);

            $dataKategoriPendapatan = $response['data'] ?? [];
            if (!$dataKategoriPendapatan) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $available = count($dataKategoriPendapatan);
            DB::beginTransaction();
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Kategori Pendapatan',
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
            foreach ($dataKategoriPendapatan as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }
                $kategoriPendapatan = KategoriPendapatan::find($data['id']);

                if ($kategoriPendapatan) {
                    $kategoriPendapatan->update([
                        'nama' => $data['deskripsi'],
                    ]);
                    $successful++;
                } else {
                    KategoriPendapatan::create([
                        'id' => $data['id'],
                        'nama' => $data['deskripsi'],
                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
                    $updated_payload = json_encode($existing_payload);
                } else {
                    $updated_payload = json_encode([(object) $data]);
                }

                $payload->update([
                    'payload' => $updated_payload,
                ]);

                $apiRequestLog->update([

                    'successful_records' => $successful,
                    'status' => $status
                ]);
            }
            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Kategori Pendapatan',
                'modul' => 'kategori pendapatan',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi kategori pendapatan berhasil'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncLayananKurir(Request $request)
    {
        try {
            $endpoint = 'layanan_kurir';
            $serverIpAddress = gethostbyname(gethostname());
            $userAgent = $request->header('User-Agent');
            $agent = new Agent();
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            $response = $apiController->makeRequest($request);

            $dataLayananKurir = $response['data'] ?? [];
            if (!$dataLayananKurir) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            $available = count($dataLayananKurir);
            DB::beginTransaction();

            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Layanan Kurir',
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

            foreach ($dataLayananKurir as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }

                $layananKurir = LayananKurir::where('nama', $data['nama_layanan'])->first();

                if ($layananKurir) {
                    $layananKurir->update([
                        'nama' => $data['nama_layanan'],
                    ]);
                    $successful++;
                } else {
                    LayananKurir::create([
                        'nama' => $data['nama_layanan'],
                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;

                if ($updated_payload !== '' || $payload->payload !== null) {
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
                    $updated_payload = json_encode($existing_payload);
                } else {
                    $updated_payload = json_encode([(object) $data]);
                }

                $payload->update([
                    'payload' => $updated_payload,
                ]);

                $apiRequestLog->update([
                    'successful_records' => $successful,
                    'status' => $status
                ]);
            }

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Layanan Kurir',
                'modul' => 'layanan kurir',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);
            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi layanan kurir berhasil'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncLayananJasaKeuangan(Request $request)
    {
        try {
            $endpoint = 'layanan_jaskug';
            $serverIpAddress = gethostbyname(gethostname());
            $userAgent = $request->header('User-Agent');
            $agent = new Agent();
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;
            $allFetchedData = [];

            $available = 0;
            $successful = 0;

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            $response = $apiController->makeRequest($request);

            $dataLayananJasaKeuangan = $response['data'] ?? [];
            if (!$dataLayananJasaKeuangan) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            $available = count($dataLayananJasaKeuangan);
            DB::beginTransaction();

            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Layanan Jasa Keuangan',
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

            foreach ($dataLayananJasaKeuangan as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }

                $layananJasaKeuangan = LayananJasaKeuangan::where('nama', $data['nama_layanan'])->first();

                if ($layananJasaKeuangan) {
                    $layananJasaKeuangan->update([
                        'nama' => $data['nama_layanan'],
                    ]);
                    $successful++;
                } else {
                    LayananJasaKeuangan::create([
                        'nama' => $data['nama_layanan'],
                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;

                if ($updated_payload !== '' || $payload->payload !== null) {
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
                    $updated_payload = json_encode($existing_payload);
                } else {
                    $updated_payload = json_encode([(object) $data]);
                }

                $payload->update([
                    'payload' => $updated_payload,
                ]);

                $apiRequestLog->update([
                    'successful_records' => $successful,
                    'status' => $status
                ]);
            }

            $userLog = [
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi Layanan Jasa Keuangan',
                'modul' => 'layanan jasa keuangan',
                'id_user' => $this->auth(),
            ];

            $userLog = UserLog::create($userLog);
            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi layanan jasa keuangan berhasil'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncPendapatan(Request $request)
    {
        try {
            $endpoint = 'pendapatan';
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'exists:kprk,id',
                'id_kpc' => 'exists:kpc,id',
                'tahun' => 'required',
                'triwulan' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], 422);
            }
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk ?? '';
            $id_kpc = $request->id_kpc ?? '';
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;

            $agent = new Agent();
            $userAgent = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();


            $list = '';
            if ($id_regional) {
                $list = Kpc::where('id_regional', $id_regional)->get();
            }
            if ($id_kprk) {
                $list = Kpc::where('id_kprk', $id_kprk)->get();
            }
            if ($id_kpc) {
                $list = Kpc::where('nomor_dirian', $id_kpc)->get();
            }
            if (!$list || $list->isEmpty()) {
                return response()->json(['error' => 'kpc not found'], 404);
            } else {
                $totalItems = $list->count();
                $userLog = [
                    'timestamp' => now(),
                    'aktifitas' => 'Sinkronisasi Pendapatan',
                    'modul' => 'Pendapatan',
                    'id_user' => $this->auth(),
                ];

                $userLog = UserLog::create($userLog);
                $job = ProcessSyncPendapatanJob::dispatch($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $triwulan, $tahun, $userAgent);

                return response()->json([
                    'status' => 'IN_PROGRESS',
                    'message' => 'Sinkronisasi sedang di proses',
                ], 200);
            }
        } catch (\Exception $e) {
            // Tangani pengecualian di sini jika diperlukan
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    private function gajiSuffix(?string $nama): string
    {
        $nama = mb_strtolower(trim((string) $nama));

        switch (true) {
            case $nama === '':
                return '00';

            case str_contains($nama, 'bisnis jasa keuangan'):
                return '01';

            case str_contains($nama, 'keuangan dan managemen risiko'):
                return '02';

            case str_contains($nama, 'human capital'):
                return '03';

            case str_contains($nama, 'operasi dan digital'):
                return '04';

            case str_contains($nama, 'business development'):
                return '05';

            case str_contains($nama, 'direktur utama'):
                return '06';

            default:
                return sprintf('%03d', crc32($nama) % 1000);
        }
    }

    public function syncMtdLtk(Request $request)
    {
        try {
            $endpoint    = 'mtd_ltk';
            $tahun       = $request->tahun;
            $bulan       = str_pad($request->bulan, 2, '0', STR_PAD_LEFT);
            $tahunbulan  = $tahun . $bulan;

            $serverIpAddress = gethostbyname(gethostname());
            $agent           = new Agent();
            $userAgent       = $request->header('User-Agent');
            $agent->setUserAgent($userAgent);
            $platform        = $agent->platform();
            $browser         = $agent->browser();
            $platform_request = $platform . '/' . $browser;

            $allFetchedData = [];
            $available = 0;
            $successful = 0;

            $apiController = new ApiController();
            $url_request   = $endpoint . '?tahunbulan=' . $tahunbulan;
            $request->merge(['end_point' => $url_request]);
            $response      = $apiController->makeRequest($request);

            $access_token = $response['access_token'] ?? null;
            $dataMtdLtk   = $response['data'] ?? [];

            if (!$dataMtdLtk) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            $available = count($dataMtdLtk);

            DB::beginTransaction();

            $apiRequestLog = ApiRequestLog::create([
                'komponen'           => 'MTD LTK',
                'tanggal'            => now(),
                'ip_address'         => $serverIpAddress,
                'platform_request'   => $platform_request,
                'successful_records' => 0,
                'available_records'  => $response['total_data'] ?? $available,
                'total_records'      => 0,
                'status'             => 'Memuat Data',
            ]);

            $payload = ApiRequestPayloadLog::create([
                'api_request_log_id' => $apiRequestLog->id,
                'payload'            => null,
            ]);

            foreach ($dataMtdLtk as $data) {
                if (empty($data)) {
                    continue;
                } else {
                    $allFetchedData[] = $data;
                }

                $periode   = $data['periode'];
                $tahunData = substr($periode, 0, 4);
                $bulanData = substr($periode, 4, 2);

                $suffix = '';
                if (!empty($data['nama_rekening']) && preg_match('/^\s*gaji/i', $data['nama_rekening'])) {
                    $suffix = $this->gajiSuffix($data['nama_rekening']);
                }
                $verifikasiId = $tahunData . $bulanData . $data['kode_rekening'] . $suffix;

                $verifikasiLtk = VerifikasiLtk::find($verifikasiId);

                if ($verifikasiLtk) {
                    $verifikasiLtk->update([
                        'tahun'                => (int) $tahunData,
                        'bulan'                => (int) $bulanData,
                        'kode_rekening'        => $data['kode_rekening'],
                        'nama_rekening'        => $data['nama_rekening'],
                        'mtd_akuntansi'        => $data['bsu_mtd_akuntansi'] ?? 0,
                        'verifikasi_akuntansi' => null,
                        'biaya_pso'            => $data['biaya_pso'],
                        'mtd_biaya_pos'        => null,
                        'mtd_biaya_hasil'      => $data['bsu_mtd_ltk'] ?? 0,
                        'proporsi_rumus'       => $data['keterangan'] ?? null,
                        'verifikasi_proporsi'  => null,
                        'id_status'            => 7,
                        'nama_file'            => null,
                        'catatan_pemeriksa'    => null,
                        'kategori_cost'        => $data['jenis'] ?? null,
                        'keterangan'           => $data['keterangan'] ?? null,
                    ]);
                    $successful++;
                } else {
                    VerifikasiLtk::create([
                        'id'                   => $verifikasiId,
                        'tahun'                => (int) $tahunData,
                        'bulan'                => (int) $bulanData,
                        'kode_rekening'        => $data['kode_rekening'],
                        'nama_rekening'        => $data['nama_rekening'],
                        'mtd_akuntansi'        => $data['bsu_mtd_akuntansi'] ?? 0,
                        'verifikasi_akuntansi' => null,
                        'biaya_pso'            => $data['biaya_pso'],
                        'verifikasi_pso'       => null,
                        'mtd_biaya_pos'        => null,
                        'mtd_biaya_hasil'      => $data['bsu_mtd_ltk'] ?? 0,
                        'proporsi_rumus'       => $data['keterangan'] ?? null,
                        'verifikasi_proporsi'  => null,
                        'id_status'            => 7,
                        'nama_file'            => null,
                        'catatan_pemeriksa'    => null,
                        'kategori_cost'        => $data['jenis'] ?? null,
                        'keterangan'           => $data['keterangan'] ?? null,
                    ]);
                    $successful++;
                }

                $status = ($successful == $available) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;

                if ($updated_payload !== '' || $payload->payload !== null) {
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];
                    $existing_payload[] = (object) $data;
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
                    'status'             => $status,
                ]);
            }

            // Log user
            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Sinkronisasi MTD LTK',
                'modul'     => 'verifikasi ltk',
                'id_user'   => $this->auth(),
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'SUCCESS',
                'message' => 'Sinkronisasi MTD LTK berhasil',
                'data'    => [
                    'available_records'  => $available,
                    'successful_records' => $successful,
                    'failed_records'     => $available - $successful,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'ERROR',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
