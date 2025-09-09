<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiLog;
use App\Models\Kprk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;

class ProcessSyncKCUJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $endpoint;
    protected $endpointProfile;
    protected $userAgent;
    public function __construct($endpoint, $endpointProfile, $userAgent)
    {

        $this->endpoint = $endpoint;
        $this->endpointProfile = $endpointProfile;

        $this->userAgent = $userAgent;
    }

    public function handle()
    {
        try {
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $agent->setUserAgent($this->userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;

            $totalTarget = 0;
            $allFetchedData = [];
            $totalSumber = 0;

            $apiController = new ApiController();
            $urlRequest = $this->endpoint;
            $request = request();
            $request->merge(['end_point' => $urlRequest]);

            $response = $apiController->makeRequest($request);

            $dataKCU = $response['data'] ?? [];

            if (!$dataKCU) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'KCU',
                'tanggal' => now(),
                'ip_address' => $serverIpAddress,
                'platform_request' => $platform_request,
                'successful_records' => 0,
                'available_records' => 0,
                'total_records' => 0,
                'status' => 'Memuat Data',
            ]);
           $payload = ApiRequestPayloadLog::create([
                'api_request_log_id' => $apiRequestLog->id,
                'payload' => null, // Store the payload as JSON
            ]);

            foreach ($dataKCU as $data) {
                $urlRequest = $this->endpointProfile . '?id_kprk=' . $data['id_kprk'];

                $request->merge(['end_point' => $urlRequest]);

                $response = $apiController->makeRequest($request);

                $profileKCU = $response['data'] ?? [];

                if (!$profileKCU) {
                    return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
                }

                foreach ($profileKCU as $data) {
                    if (empty($profileKCU)) {
                        continue;
                    } else {
                        $allFetchedData[] = $data;
                        $totalTarget++;
                    }
                }
            }


            $status = 'on progress';
            if ($allFetchedData == []) {
                $status = 'data tidak tersedia';
            }
            $apiRequestLog->update([
                'total_records'=> $totalTarget,
                'available_records' => $response['total_data'] ??$totalTarget,
                'status' => $status,
            ]);
            foreach ($allFetchedData as $data) {
                $existingKCU = Kprk::find($data['id_kprk']);

                $kprkData = [
                    'id_regional' => $data['regional'],
                    'nama' => $data['nama_kprk'],
                    'id_provinsi' => $data['provinsi'] ?? 0,
                    'id_kabupaten_kota' => $data['kab_kota'] ?? 0,
                    'id_kecamatan' => $data['kecamatan'] ?? 0,
                    'id_kelurahan' => $data['kelurahan'] ?? 0,
                    'jumlah_kpc_lpu' => $data['jumlah_kpc_lpu'],
                    'jumlah_kpc_lpk' => $data['jumlah_kpc_lpk'],
                    'tgl_sinkronisasi' => now(),
                ];

                if ($existingKCU) {
                    $existingKCU->update($kprkData);
                } else {
                    Kprk::create(array_merge(['id' => $data['id_kprk']], $kprkData));
                }

                $totalSumber++;
                $status = ($totalSumber == $totalTarget) ? 'success' : 'on progress';
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
                    'successful_records' => $totalSumber,
                    'status' => $status,
                ]);
            }

        } catch (\Exception $e) {
            dd($e);
        }
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
    return 0; // No timeout for this job
}

}
