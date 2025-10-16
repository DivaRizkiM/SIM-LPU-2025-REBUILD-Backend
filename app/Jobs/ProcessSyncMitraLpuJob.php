<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\MitraLpu;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;

class ProcessSyncMitraLpuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** contoh: 'mitra_lpu' (tanpa query) */
    protected string $endpointBase;

    /** ID KPC yang digunakan sebagai nopend_kpc */
    protected string $idKpc;

    protected ?string $userAgent;

    public function __construct(string $endpointBase, string $idKpc, ?string $userAgent = null)
    {
        $this->endpointBase = trim($endpointBase);
        $this->idKpc        = trim($idKpc);
        $this->userAgent    = $userAgent;
    }

    public function handle(): void
    {
        $urlRequest = "{$this->endpointBase}?nopend_kpc={$this->idKpc}";

        // --- ApiRequestLog (pakai kolom yang ada: komponen, dst.)
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        if ($this->userAgent) {
            $agent->setUserAgent($this->userAgent);
        }
        $platformRequest = $agent->platform() . '/' . $agent->browser();

        $apiLog = ApiRequestLog::create([
            'komponen'            => 'Mitra LPU',
            'tanggal'             => now(),
            'ip_address'          => $serverIpAddress,
            'platform_request'    => $platformRequest,
            'successful_records'  => 0,
            'available_records'   => 0,
            'total_records'       => 0,
            'status'              => 'Memuat Data',
        ]);

        $payload = ApiRequestPayloadLog::create([
            'api_request_log_id' => $apiLog->id,
            'payload'            => null,
        ]);

        try {
            // --- Panggil API
            $apiController = new ApiController();
            $req = new Request();
            if ($this->userAgent) {
                $req->headers->set('User-Agent', $this->userAgent);
            }
            $req->merge(['end_point' => $urlRequest]);

            $resp = $apiController->makeRequest($req);
            $rows = $this->extractRows($resp);

            $apiLog->update([
                'available_records' => $resp['total_data'] ?? count($rows),
                'total_records'     => count($rows),
                'status'            => empty($rows) ? 'data tidak tersedia' : 'on progress',
            ]);

            $ok = 0;

            foreach ($rows as $row) {
                $norm = $this->normalizeRow($row);

                // Upsert (id_kpc, nopend, nib)
                MitraLpu::updateOrCreate(
                    [
                        'id_kpc' => $this->idKpc,
                        'nopend' => $norm['nopend'],
                        'nib'    => $norm['nib'],
                    ],
                    [
                        'nama_mitra'         => $norm['nama_mitra'],
                        'alamat_mitra'       => $norm['alamat_mitra'],
                        'kode_wilayah_kerja' => $norm['kode_wilayah_kerja'],
                        'nama_wilayah'       => $norm['nama_wilayah'],
                        'lat'                => $norm['lat'],
                        'long'               => $norm['long'],
                        'nik'                => $norm['nik'],
                        'namafile'           => $norm['namafile'],
                        'raw'                => $row,
                    ]
                );

                // Append payload history
                $updated = $payload->payload ? json_decode($payload->payload, true) : [];
                if (!is_array($updated)) $updated = [$updated];
                $row['_synced_at'] = now()->toIso8601String();
                $updated[] = $row;
                $payload->update(['payload' => json_encode($updated)]);

                $ok++;
                usleep(200_000); // throttle 0.2s
            }

            $apiLog->update([
                'successful_records' => $ok,
                'status'             => 'success',
            ]);
        } catch (\Throwable $e) {
            $apiLog->update(['status' => 'gagal']);
            Log::error('ProcessSyncMitraLpuJob failed', [
                'endpoint' => $this->endpointBase,
                'id_kpc'   => $this->idKpc,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function extractRows($resp): array
    {
        if (!is_array($resp)) return [];
        $data = $resp['data'] ?? [];

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        if (is_array($data) && (empty($data) || is_array(reset($data)))) {
            return $data;
        }
        return [];
    }

    protected function normalizeRow(array $row): array
    {
        $toFloat = function ($v) {
            if ($v === null || $v === '') return null;
            $v = str_replace([' ', ','], ['', '.'], (string) $v);
            return is_numeric($v) ? (float) $v : null;
        };

        return [
            'nib'                => (string)($row['nib'] ?? $row['NIB'] ?? ''),
            'nama_mitra'         => (string)($row['nama_mitra'] ?? $row['NamaMitra'] ?? ''),
            'alamat_mitra'       => (string)($row['alamat_mitra'] ?? $row['AlamatMitra'] ?? ''),
            'kode_wilayah_kerja' => (string)($row['kode_wilayah_kerja'] ?? $row['KodeWilayahKerja'] ?? ''),
            'nama_wilayah'       => (string)($row['nama_wilayah'] ?? $row['NamaWilayah'] ?? ''),
            'nopend'             => (string)($row['nopend'] ?? $row['NOPEND'] ?? ''),
            'lat'                => $toFloat($row['lat'] ?? $row['latitude'] ?? $row['Lat'] ?? null),
            'long'               => $toFloat($row['long'] ?? $row['longitude'] ?? $row['Long'] ?? null),
            'nik'                => (string)($row['nik'] ?? $row['NIK'] ?? ''),
            'namafile'           => (string)($row['namafile'] ?? $row['nama_file'] ?? $row['NamaFile'] ?? ''),
        ];
    }

    public function maxAttempts(): int
    {
        return 5;
    }
    public function backoff(): int
    {
        return 10;
    } // detik
    public function timeout(): int
    {
        return 0;
    }  // tanpa timeout
}
