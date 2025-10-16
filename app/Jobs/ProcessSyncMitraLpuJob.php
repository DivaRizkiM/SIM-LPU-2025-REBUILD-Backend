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
use Illuminate\Http\Request as HttpRequest;
use Jenssegers\Agent\Agent;

class ProcessSyncMitraLpuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string endpoint dasar, contoh: 'mitra_lpu' */
    protected string $endpoint;

    /** @var string NOPEND KPC (== nomor_dirian) */
    protected string $nopendKpc;

    /** @var string|null */
    protected ?string $userAgent;

    public function __construct(string $endpoint, string $nopendKpc, ?string $userAgent = null)
    {
        $this->endpoint  = $endpoint;
        $this->nopendKpc = $nopendKpc;
        $this->userAgent = $userAgent;
    }

    public function handle(): void
    {
        $urlRequest = $this->endpoint . '?nopend_kpc=' . $this->nopendKpc;

        // Logging request header/platform (sesuai pola controller lain)
        $serverIpAddress = gethostbyname(gethostname());
        $agent           = new Agent();
        if ($this->userAgent) $agent->setUserAgent($this->userAgent);
        $platform_request = trim(($agent->platform() ?? '') . '/' . ($agent->browser() ?? ''), '/');

        // Buat rekap log awal
        $apiLog = ApiRequestLog::create([
            'komponen'           => 'Mitra LPU',
            'tanggal'            => now(),
            'ip_address'         => $serverIpAddress,
            'platform_request'   => $platform_request,
            'successful_records' => 0,
            'available_records'  => 0,
            'total_records'      => 0,
            'status'             => 'Memuat Data',
        ]);

        $payloadLog = ApiRequestPayloadLog::create([
            'api_request_log_id' => $apiLog->id,
            'payload'            => null,
        ]);

        try {
            // Panggil API via ApiController yang sudah ada
            $apiController = new ApiController();
            $req = new HttpRequest();
            if ($this->userAgent) {
                // simulasikan UA di job
                $req->headers->set('User-Agent', $this->userAgent);
            }
            $req->merge(['end_point' => $urlRequest]);

            $resp = $apiController->makeRequest($req);

            $rows = $this->extractRows($resp);

            $available = $resp['total_data'] ?? count($rows);
            $apiLog->update([
                'available_records' => $available,
                'total_records'     => count($rows),
                'status'            => empty($rows) ? 'data tidak tersedia' : 'on progress',
            ]);

            $ok = 0;

            foreach ($rows as $row) {
                $payload = $this->normalizeRow($row);

                // Upsert: id_kpc = NOPEND KPC (nomor_dirian)
                MitraLpu::updateOrCreate(
                    [
                        'id_kpc' => $this->nopendKpc,
                        'nopend' => $payload['nopend'],
                        'nib'    => $payload['nib'],
                    ],
                    [
                        'nama_mitra'         => $payload['nama_mitra'],
                        'alamat_mitra'       => $payload['alamat_mitra'],
                        'kode_wilayah_kerja' => $payload['kode_wilayah_kerja'],
                        'nama_wilayah'       => $payload['nama_wilayah'],
                        'lat'                => $payload['lat'],
                        'long'               => $payload['long'],
                        'nik'                => $payload['nik'],
                        'namafile'           => $payload['namafile'],
                        'raw'                => $row, // simpan raw utk audit
                    ]
                );

                // simpan payload historis
                $updated_payload = $payloadLog->payload ?? '';
                $jsonData = json_encode($row);
                $row['_synced_at'] = now()->toIso8601String();
                $row['size'] = strlen($jsonData);

                if ($updated_payload !== '' || $payloadLog->payload !== null) {
                    $existing = json_decode($updated_payload, true);
                    $existing = is_array($existing) ? $existing : [$existing];
                    $existing[] = (object)$row;
                    $updated_payload = json_encode($existing);
                } else {
                    $updated_payload = json_encode([(object)$row]);
                }

                $payloadLog->update(['payload' => $updated_payload]);

                $ok++;
                usleep(250_000); // throttle
            }

            $apiLog->update([
                'successful_records' => $ok,
                'status'             => $ok > 0 ? 'success' : 'data tidak tersedia',
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessSyncMitraLpuJob failed', [
                'endpoint' => $this->endpoint,
                'nopend'   => $this->nopendKpc,
                'error'    => $e->getMessage(),
            ]);
            throw $e; // biar retry sesuai konfigurasi queue
        }
    }

    /** Ambil array baris data dari response API */
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

    /** Normalisasi 1 row ke struktur kolom DB */
    protected function normalizeRow(array $row): array
    {
        $toFloat = function ($v) {
            if ($v === null || $v === '') return null;
            $v = str_replace([' ', ','], ['', '.'], (string) $v);
            return is_numeric($v) ? (float) $v : null;
        };

        return [
            'nib'                => (string)($row['nib'] ?? $row['NIB'] ?? ''),
            'nama_mitra'         => (string)($row['nama_mitra'] ?? $row['NamaMitra'] ?? $row['Nama_Mitra'] ?? ''),
            'alamat_mitra'       => (string)($row['alamat_mitra'] ?? $row['AlamatMitra'] ?? $row['Alamat_Mitra'] ?? ''),
            'kode_wilayah_kerja' => (string)($row['kode_wilayah_kerja'] ?? $row['KodeWilayahKerja'] ?? ''),
            'nama_wilayah'       => (string)($row['nama_wilayah'] ?? $row['NamaWilayah'] ?? ''),
            'nopend'             => (string)($row['nopend'] ?? $row['Nopend'] ?? $row['NOPEND'] ?? ''),
            'lat'                => $toFloat($row['lat'] ?? $row['Lat'] ?? $row['latitude'] ?? null),
            'long'               => $toFloat($row['long'] ?? $row['Long'] ?? $row['longitude'] ?? null),
            'nik'                => (string)($row['nik'] ?? $row['NIK'] ?? ''),
            'namafile'           => (string)($row['namafile'] ?? $row['NamaFile'] ?? $row['nama_file'] ?? ''),
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
