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

    /** Base endpoint tanpa query, contoh: 'mitra_lpu' */
    protected string $endpointBase;

    /** NOPEND KPC (id_kpc yg dipakai API) */
    protected string $idKpc;

    /** Optional UA yang diteruskan ke ApiController */
    protected ?string $userAgent;

    public function __construct(string $endpointBase, string $idKpc, ?string $userAgent = null)
    {
        $this->endpointBase = rtrim($endpointBase, '?');
        $this->idKpc        = $idKpc;
        $this->userAgent    = $userAgent;
    }

    public function handle(): void
    {
        $urlRequest = "{$this->endpointBase}?nopend_kpc={$this->idKpc}";

        // ----- ApiRequestLog (TANPA kolom 'endpoint') -----
        $serverIpAddress  = gethostbyname(gethostname());
        $agent            = new \Jenssegers\Agent\Agent();
        if ($this->userAgent) {
            $agent->setUserAgent($this->userAgent);
        }
        $platformRequest  = $agent->platform().'/'.$agent->browser();

        $apiLog = ApiRequestLog::create([
            'komponen'           => 'Mitra LPU',
            'tanggal'            => now(),
            'ip_address'         => $serverIpAddress,
            'platform_request'   => $platformRequest,
            'successful_records' => 0,
            'available_records'  => 0,
            'total_records'      => 0,
            'status'             => 'Memuat Data',
        ]);

        // Satu payload log untuk 1 job ini
        $payloadLog = ApiRequestPayloadLog::create([
            'api_request_log_id' => $apiLog->id,
            'payload'            => null,
        ]);

        try {
            $apiController = new ApiController();
            $req = request();
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
                $payload = $this->normalizeRow($row);

                // Upsert unik by: id_kpc (nopend), nopend, nib
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
                        'raw'                => $row,
                    ]
                );

                // Tambah ke payload log
                $updatedPayload = $payloadLog->payload ?? '';
                $newItem = (object) array_merge($row, ['_synced_at' => now()->toIso8601String()]);
                if ($updatedPayload !== '' && $payloadLog->payload !== null) {
                    $arr = json_decode($updatedPayload, true);
                    $arr = is_array($arr) ? $arr : [$arr];
                    $arr[] = $newItem;
                    $payloadLog->update(['payload' => json_encode($arr)]);
                } else {
                    $payloadLog->update(['payload' => json_encode([$newItem])]);
                }

                $ok++;
                usleep(250_000); // rate-limit guard
            }

            $apiLog->update([
                'successful_records' => $ok,
                'status'             => empty($rows) ? 'data tidak tersedia' : 'success',
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

    /** Ambil array baris dari response API yang mungkin bervariasi strukturnya */
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

    /** Normalisasi 1 row ke struktur field DB */
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

    public function maxAttempts(): int { return 5; }
    public function backoff(): int { return 10; }       // detik
    public function timeout(): int { return 0; }        // tanpa timeout
}
