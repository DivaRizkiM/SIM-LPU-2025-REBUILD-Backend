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

class ProcessSyncMitraLpuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string Base endpoint tanpa query, contoh: https://host/pso/1.0.0/data/mitra_lpu */
    protected string $endpointBase;

    /** @var string ID KPC yang akan disinkron (== nopend_kpc) */
    protected string $idKpc;

    /** @var string|null */
    protected ?string $userAgent;

    public function __construct(string $endpointBase, string $idKpc, ?string $userAgent = null)
    {
        $this->endpointBase = rtrim($endpointBase, '?');
        $this->idKpc        = $idKpc;
        $this->userAgent    = $userAgent;
    }

    public function handle(): void
    {
        // Siapkan endpoint final
        $urlRequest = "{$this->endpointBase}?nopend_kpc={$this->idKpc}";

        // Log request summary (atau pakai ApiRequestLog jika mau rekap global)
        $apiLog = ApiRequestLog::firstOrCreate(
            ['endpoint' => $this->endpointBase],
            ['total_records' => 0, 'available_records' => 0, 'status' => 'on progress', 'successful_records' => 0]
        );

        try {
            // Panggil API via controller util yang sudah ada di project-mu
            $apiController = new ApiController();
            $req = request();
            $req->headers->set('User-Agent', $this->userAgent ?? $req->userAgent());
            $req->merge(['end_point' => $urlRequest]);

            $resp = $apiController->makeRequest($req);
            // Bentuk umum yang diharapkan:
            // $resp = [
            //   'success' => '00000' | 'true' | 'success' | etc,
            //   'message' => 'ok',
            //   'total_data' => 12,
            //   'data' => [ { nib, nama_mitra, ... }, ... ]
            // ]

            $rows = $this->extractRows($resp);

            $apiLog->update([
                'available_records' => $resp['total_data'] ?? count($rows),
                'total_records'     => ($apiLog->total_records ?? 0) + count($rows),
                'status'            => empty($rows) ? 'data tidak tersedia' : 'on progress',
            ]);

            $ok = 0;

            foreach ($rows as $row) {
                // Map field dari response ke kolom DB
                $payload = $this->normalizeRow($row);

                // Upsert berdasarkan unique key: id_kpc, nopend, nib
                MitraLpu::updateOrCreate(
                    [
                        'id_kpc' => $this->idKpc,
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
                        'raw'                => $row, // simpan mentah untuk audit
                    ]
                );

                // Simpan payload historis per KPC (opsional, mengikuti polamu)
                $payloadLog = ApiRequestPayloadLog::firstOrCreate(
                    ['kpc_id' => $this->idKpc],
                    ['payload' => null]
                );

                $existing = $payloadLog->payload;
                $newItem  = (object) array_merge($row, ['_synced_at' => now()->toIso8601String()]);
                if ($existing) {
                    $arr = json_decode($existing, true);
                    $arr = is_array($arr) ? $arr : [$arr];
                    $arr[] = $newItem;
                    $payloadLog->update(['payload' => json_encode($arr)]);
                } else {
                    $payloadLog->update(['payload' => json_encode([$newItem])]);
                }

                $ok++;
                // Hindari rate limit
                usleep(250_000);
            }

            $apiLog->update([
                'successful_records' => ($apiLog->successful_records ?? 0) + $ok,
                'status'             => 'success',
            ]);

        } catch (\Throwable $e) {
            Log::error('ProcessSyncMitraLpuJob failed', [
                'endpoint' => $this->endpointBase,
                'id_kpc'   => $this->idKpc,
                'error'    => $e->getMessage(),
            ]);
            // Re-throw supaya queue dapat retry sesuai konfigurasi job
            throw $e;
        }
    }

    /**
     * Ambil array baris data dari response API; tahan variasi bentuk.
     */
    protected function extractRows($resp): array
    {
        if (!is_array($resp)) return [];
        $data = $resp['data'] ?? [];
        // Kalau 'data' berupa object seperti ['data' => ['items' => [...]]]
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        // Jika 'data' sudah berupa array of objects
        if (is_array($data) && (empty($data) || is_array(reset($data)))) {
            return $data;
        }
        return [];
    }

    /**
     * Normalisasi 1 row ke struktur yang sesuai kolom DB.
     */
    protected function normalizeRow(array $row): array
    {
        // Helper numeric
        $toFloat = function ($v) {
            if ($v === null || $v === '') return null;
            // Hilangkan koma/desimal lokal
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
        return 10; // detik
    }

    public function timeout(): int
    {
        return 0; // tanpa timeout
    }
}
