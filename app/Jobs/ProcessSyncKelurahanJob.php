<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\Kelurahan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;

class ProcessSyncKelurahanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $endpoint;
    protected $userAgent;
    protected $page;
    protected $perPage;

    public function __construct($endpoint, $userAgent, $page = 1, $perPage = 1000)
    {
        $this->endpoint = $endpoint;
        $this->userAgent = $userAgent;
        $this->page = $page;
        $this->perPage = $perPage;
    }

    public function handle()
    {
        // setiap job memproses 1 halaman (batch) => rendah memory footprint
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        $agent->setUserAgent($this->userAgent);
        $platform = $agent->platform();
        $browser = $agent->browser();
        $platform_request = $platform . '/' . $browser;

        $apiController = new ApiController();
        // buat Request terpisah agar tidak tergantung request global
        $req = Request::create('/', 'GET', [
            'end_point' => $this->endpoint,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ]);

        $response = $apiController->makeRequest($req);
        $dataKelurahan = $response['data'] ?? [];

        if (empty($dataKelurahan)) {
            // tidak ada data pada halaman ini => segera selesai
            return;
        }

        // Buat log minimal untuk batch ini
        $apiRequestLog = ApiRequestLog::create([
            'komponen' => 'Kelurahan',
            'tanggal' => now(),
            'ip_address' => $serverIpAddress,
            'platform_request' => $platform_request,
            'successful_records' => 0,
            // total_records = total keseluruhan dari API (jika tersedia)
            'total_records' => $response['total_data'] ?? count($dataKelurahan),
            // available_records = jumlah data pada halaman ini (batch), bukan total keseluruhan
            'available_records' => count($dataKelurahan),
            'status' => 'Memproses Batch',
        ]);

        // Simpan ringkasan payload (contoh small sample) — bukan seluruh dump
        $samplePayload = isset($dataKelurahan[0]) ? json_encode($dataKelurahan[0]) : null;
        $payloadLog = ApiRequestPayloadLog::create([
            'api_request_log_id' => $apiRequestLog->id,
            'payload' => $samplePayload,
        ]);

        // Persiapkan batch untuk upsert
        $batch = [];
        foreach ($dataKelurahan as $d) {
            if (empty($d)) continue;
            $batch[] = [
                'id' => $d['kode_kelurahan'],
                'nama' => $d['nama_kelurahan'] ?? null,
                'id_kecamatan' => $d['kode_kecamatan'] ?? null,
                'id_kabupaten_kota' => $d['kode_kota_kab'] ?? null,
                'id_provinsi' => $d['kode_provinsi'] ?? null,
            ];
        }

        // Lakukan DB transaction untuk batch upsert (gunakan chunk agar memory stabil)
        DB::transaction(function () use ($batch, $apiRequestLog) {
            if (!empty($batch)) {
                $processed = 0;
                foreach (array_chunk($batch, 500) as $chunk) {
                    Kelurahan::upsert($chunk, ['id'], ['nama', 'id_kecamatan', 'id_kabupaten_kota', 'id_provinsi', 'updated_at']);
                    $processed += count($chunk);
                }

                $apiRequestLog->update([
                    'successful_records' => $processed,
                    'status' => 'success',
                ]);
            } else {
                $apiRequestLog->update([
                    'status' => 'no_data',
                ]);
            }
        });

        // free memory
        unset($dataKelurahan, $batch);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    public function maxAttempts()
    {
        return 5;
    }

    public function backoff()
    {
        return 10;
    }

    public function timeout()
    {
        return 0;
    }
}
