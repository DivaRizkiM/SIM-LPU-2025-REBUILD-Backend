<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiLog;
use App\Models\PetugasKPC;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Support\Facades\Log;

class ProcessSyncPetugasKCPJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $endpoint;
    protected $endpointPetugas;
    protected $userAgent;

    public function __construct($endpoint, $endpointPetugas, $userAgent)
    {
        $this->endpoint = $endpoint;
        $this->endpointPetugas = $endpointPetugas;
        $this->userAgent = $userAgent;
    }

    public function handle()
    {
        $apiRequestLog = null;

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
            $dataKPC = $response['data'] ?? [];

            if (!$dataKPC) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Petugas KCP',
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
                'payload' => null,
            ]);

            foreach ($dataKPC as $data) {
                $urlRequest = $this->endpointPetugas . '?id_kpc=' . $data['nopend'];
                $request->merge(['end_point' => $urlRequest]);

                $response = $apiController->makeRequest($request);
                $petugasKCP = $response['data'] ?? [];

                foreach ($petugasKCP as $data) {
                    if (empty($petugasKCP)) {
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
                'total_records' => $totalTarget,
                'available_records' => $response['total_data'] ?? $totalTarget,
                'status' => $status,
            ]);

            foreach ($allFetchedData as $data) {
                $petugasKPC = PetugasKPC::find($data['idpetugas']);
                $kpcData = [
                    'nama_petugas' => $data['nama_petugas'],
                    'pangkat' => $data['pangkat'],
                    'masa_kerja' => $data['masa_kerja'],
                    'jabatan' => $data['jabatan'],
                ];

                if ($petugasKPC) {
                    $petugasKPC->update($kpcData);
                } else {
                    PetugasKPC::create(array_merge(
                        ['id_kpc' => $data['id_kpc']],
                        ['id' => $data['idpetugas']],
                        ['nippos' => $data['nippos']],
                        $kpcData
                    ));
                }

                $totalSumber++;
                $status = ($totalSumber == $totalTarget) ? 'success' : 'on progress';

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
                    'successful_records' => $totalSumber,
                    'status' => $status,
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            // ✅ Update log status jadi gagal kalau error
            if ($apiRequestLog) {
                $apiRequestLog->update([
                    'status' => 'gagal',
                ]);
            }

            Log::error('Job ProcessSyncPetugasKCPJob gagal: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Tetap lempar agar Laravel tahu ini failed
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
