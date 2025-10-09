<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\Kecamatan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;

class ProcessSyncKecamatanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $endpoint;
    protected $userAgent;
    protected $page;
    protected $perPage;

    public function __construct($endpoint, $userAgent, $page = 1, $perPage = 8000)
    {
        $this->endpoint = $endpoint;
        $this->userAgent = $userAgent;
        $this->page = $page;
        $this->perPage = $perPage;
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

            $apiController = new ApiController();
            $req = Request::create('/', 'GET', [
                'end_point' => $this->endpoint,
                'page' => $this->page,
                'per_page' => $this->perPage,
            ]);

            $response = $apiController->makeRequest($req);
            $data = $response['data'] ?? [];

            if (empty($data)) {
                return;
            }

            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Kecamatan',
                'tanggal' => now(),
                'ip_address' => $serverIpAddress,
                'platform_request' => $platform_request,
                'successful_records' => 0,
                'total_records' => $response['total_data'] ?? count($data),
                'available_records' => count($data),
                'status' => 'Memproses Batch',
            ]);

            $samplePayload = isset($data[0]) ? json_encode($data[0]) : null;
            ApiRequestPayloadLog::create([
                'api_request_log_id' => $apiRequestLog->id,
                'payload' => $samplePayload,
            ]);

            // cek apakah model menggunakan timestamps
            $model = new Kecamatan();
            $useTimestamps = property_exists($model, 'timestamps') ? $model->timestamps : true;

            $batch = [];
            foreach ($data as $d) {
                if (empty($d)) continue;
                $row = [
                    'id' => $d['kode_kecamatan'],
                    'nama' => $d['nama_kecamatan'] ?? null,
                    'id_kabupaten_kota' => $d['kode_kota_kab'] ?? null,
                    'id_provinsi' => $d['kode_provinsi'] ?? null,
                ];
                if ($useTimestamps) {
                    $row['created_at'] = now();
                    $row['updated_at'] = now();
                }
                $batch[] = $row;
            }

            DB::transaction(function () use ($batch, $apiRequestLog, $useTimestamps) {
                if (!empty($batch)) {
                    $processed = 0;
                    $updateCols = ['nama', 'id_kabupaten_kota', 'id_provinsi'];
                    if ($useTimestamps) {
                        $updateCols[] = 'updated_at';
                    }

                    foreach (array_chunk($batch, 500) as $chunk) {
                        Kecamatan::upsert($chunk, ['id'], $updateCols);
                        $processed += count($chunk);
                    }

                    $apiRequestLog->update([
                        'successful_records' => $processed,
                        'status' => 'success',
                    ]);
                } else {
                    $apiRequestLog->update(['status' => 'no_data']);
                }
            });

            unset($data, $batch);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        } catch (\Throwable $e) {
            if (isset($apiRequestLog) && $apiRequestLog instanceof ApiRequestLog) {
                try { $apiRequestLog->update(['status' => 'failed']); } catch (\Throwable $_) {}
            }
            \Log::error('ProcessSyncKecamatanJob failed', [
                'endpoint' => $this->endpoint,
                'page' => $this->page,
                'perPage' => $this->perPage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function maxAttempts() { return 5; }
    public function backoff() { return 10; }
    public function timeout() { return 0; }
}