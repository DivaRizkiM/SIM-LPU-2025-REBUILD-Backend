<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\VerifikasiBiayaRutinDetailLampiran;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Jenssegers\Agent\Agent;

class RsyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $list;
    protected string $endpoint;
    protected string $userAgent;
    protected int $totalData;

    // === Counters akurat ===
    protected int $processedCount = 0;        // berapa ID diproses (loop)
    protected int $downloadedCount = 0;       // berapa file benar-benar tersalin (>0 bytes)
    protected int $apiEmptyCount = 0;         // API tidak mengembalikan dataLampiran / nama_file
    protected int $remoteMissingCount = 0;    // file tidak ketemu di server rsync
    protected int $errorCount = 0;            // error lain (rsync binary, exec, dll.)

    public function __construct($list, $endpoint, $totalData, $userAgent)
    {
        $this->list = $list;
        $this->endpoint = (string) $endpoint;
        $this->totalData = (int) $totalData;
        $this->userAgent = (string) $userAgent;
    }

    public function handle()
    {
        try {
            Log::info('RsyncJob started.', ['total_data' => $this->totalData, 'endpoint' => $this->endpoint]);

            if ($this->totalData === 0) {
                Log::warning('RsyncJob stopped: totalData is zero.');
                $this->createApiRequestLog(0); // untuk visibilitas
                return;
            }

            $apiRequestLog = $this->createApiRequestLog($this->totalData);
            $payload = $this->initializePayload($apiRequestLog);

            // Pastikan destinasi ada
            Storage::makeDirectory('public/lampiran');

            foreach ($this->list as $ls) {
                $this->processedCount++;

                $url_request = $this->endpoint . '?id_biaya=' . $ls->id;
                Log::info('Fetching data for URL', ['url' => $url_request]);

                $response = $this->fetchData($url_request);
                $dataLampiran = $response['data'] ?? [];

                if (empty($dataLampiran) || empty($dataLampiran['nama_file'])) {
                    $this->apiEmptyCount++;
                    $this->updatePayload([
                        'error'     => 'data tidak tersedia dari API',
                        'id_biaya'  => $ls->id,
                    ], $payload);
                    $this->updateApiRequestLogProgress($apiRequestLog, $payload);
                    continue;
                }

                // Simpan/Update meta lampiran
                $verifikasi = VerifikasiBiayaRutinDetailLampiran::updateOrCreate(
                    ['id' => $dataLampiran['id']],
                    [
                        'nama_file' => $dataLampiran['nama_file'],
                        // jika FK sebenarnya beda, sesuaikan:
                        'verifikasi_biaya_rutin_detail' => $dataLampiran['id'],
                    ]
                );

                // Sync file
                $bytes = $this->syncFileSmart($dataLampiran['nama_file']);
                if ($bytes > 0) {
                    $this->downloadedCount++;
                    $dataLampiran['size'] = $bytes;
                    $this->updatePayload($dataLampiran, $payload);
                } else {
                    $this->remoteMissingCount++;
                    $this->updatePayload([
                        'error'     => 'file tidak ditemukan/0 bytes setelah rsync',
                        'id'        => $dataLampiran['id'] ?? null,
                        'id_biaya'  => $dataLampiran['id_biaya'] ?? null,
                        'nama_file' => $dataLampiran['nama_file'],
                    ], $payload);
                }

                // Jika ekstensi di DB tidak valid tapi file lokal ada dgn ekstensi lain → benarkan di DB
                if ($bytes > 0) {
                    $fixed = $this->fixDbFilenameIfNeeded($dataLampiran['nama_file']);
                    if ($fixed && $fixed !== $dataLampiran['nama_file']) {
                        $verifikasi->update(['nama_file' => $fixed]);
                    }
                }

                $this->updateApiRequestLogProgress($apiRequestLog, $payload);
            }

            // Finalize log
            $this->finalizeApiRequestLog($apiRequestLog, $payload);

            Log::info('RsyncJob finished.', [
                'processed'  => $this->processedCount,
                'downloaded' => $this->downloadedCount,
                'api_empty'  => $this->apiEmptyCount,
                'remote_missing' => $this->remoteMissingCount,
                'errors'     => $this->errorCount,
            ]);
        } catch (Exception $e) {
            Log::error('RsyncJob exception: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            throw $e;
        }
    }

    /* =========================
     *  API Request Log helpers
     * ========================= */
    protected function createApiRequestLog(int $totalData): ApiRequestLog
    {
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        $agent->setUserAgent($this->userAgent);
        $platformRequest = $agent->platform() . '/' . $agent->browser();

        return ApiRequestLog::create([
            'komponen'           => 'Lampiran Biaya',
            'tanggal'            => now(),
            'ip_address'         => $serverIpAddress,
            'platform_request'   => $platformRequest,
            'successful_records' => 0,                  // diisi jumlah file sukses (bukan loop)
            'available_records'  => $totalData,
            'total_records'      => $totalData,
            'status'             => 'on progress',
        ]);
    }

    protected function initializePayload(ApiRequestLog $apiRequestLog): ApiRequestPayloadLog
    {
        return ApiRequestPayloadLog::create([
            'api_request_log_id' => $apiRequestLog->id,
            'payload' => null,
        ]);
    }

    protected function updateApiRequestLogProgress(ApiRequestLog $apiRequestLog, ApiRequestPayloadLog $payload): void
    {
        $status = ($this->processedCount >= $this->totalData) ? 'success' : 'on progress';

        $apiRequestLog->update([
            'successful_records' => $this->downloadedCount, // yang benar-benar masuk
            'status'             => $status,
        ]);
    }

    protected function finalizeApiRequestLog(ApiRequestLog $apiRequestLog, ApiRequestPayloadLog $payload): void
    {
        $summary = [
            'processed'       => $this->processedCount,
            'downloaded'      => $this->downloadedCount,
            'api_empty'       => $this->apiEmptyCount,
            'remote_missing'  => $this->remoteMissingCount,
            'errors'          => $this->errorCount,
        ];
        $this->updatePayload(['summary' => $summary], $payload);

        $apiRequestLog->update([
            'successful_records' => $this->downloadedCount,
            'status'             => 'success',
        ]);
    }

    /* =================
     *  Fetch from API
     * ================= */
    protected function fetchData(string $url_request): array
    {
        $apiController = new ApiController();
        $request = request();
        $request->merge(['end_point' => $url_request]);

        $res = $apiController->makeRequest($request);
        return is_array($res) ? $res : [];
    }

    /* =========================
     *  Rsync helpers & logic
     * ========================= */

    /**
     * Sync file dengan “ext-aware” + retry.
     * Return ukuran file lokal (>0) jika sukses, 0 jika gagal.
     */
    protected function syncFileSmart(string $namaFile): int
    {
        $destinationPath = storage_path('app/public/lampiran');
        if (!is_dir($destinationPath)) {
            Storage::makeDirectory('public/lampiran');
        }

        // Konfigurasi rsync dari ENV
        $rsyncHost     = '147.139.133.1';
        $rsyncPort     = 8732;
        $rsyncUser     = 'kominfo2';
        $rsyncPassword = 'k0minf0!';
        $rsyncModule   = 'lpu';
        $rsyncBasePath = '/'; // kosong = root modul
        $timeout       = 12;
        $maxRetry      = 3;

        // Cek rsync tersedia
        $linesCheck = [];
        $codeCheck  = 0;
        @exec('command -v rsync || which rsync', $linesCheck, $codeCheck);
        if ($codeCheck !== 0 || empty($linesCheck)) {
            Log::error('Rsync binary not found.');
            $this->errorCount++;
            return 0;
        }

        // Sanitasi nama file untuk menghindari karakter berbahaya di shell (tetap pakai escapeshellarg, ini hanya sanity)
        $namaFile = trim($namaFile);
        if ($namaFile === '' || preg_match('/[^\w\.\-]/', $namaFile)) {
            Log::warning('Invalid filename pattern', ['nama_file' => $namaFile]);
            return 0;
        }

        $fileInfo = pathinfo($namaFile);
        $filenameWithoutExt = $fileInfo['filename'] ?? $namaFile;
        $currentExt = $fileInfo['extension'] ?? '';

        // Urutan ekstensi yang dicoba — minimal dulu biar cepat
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
        $candidates = [];

        if ($currentExt !== '') {
            // coba yang sesuai dulu (case-insensitive)
            $candidates[] = $filenameWithoutExt . '.' . $currentExt;
            if (strtolower($currentExt) !== $currentExt) {
                $candidates[] = $filenameWithoutExt . '.' . strtolower($currentExt);
            }
            if (strtoupper($currentExt) !== $currentExt) {
                $candidates[] = $filenameWithoutExt . '.' . strtoupper($currentExt);
            }
        } else {
            // tidak ada ext → coba beberapa favorit
            foreach ($allowed as $ext) {
                $candidates[] = $filenameWithoutExt . '.' . $ext;
                $candidates[] = $filenameWithoutExt . '.' . strtoupper($ext);
            }
        }

        // Hilangkan duplikat
        $candidates = array_values(array_unique($candidates));

        // Prefix path remote (modul + base path opsional)
        $remotePrefix = $rsyncModule . '/';
        if ($rsyncBasePath !== '') {
            $remotePrefix .= $rsyncBasePath . '/';
        }

        foreach ($candidates as $fileToSync) {
            $remote = "{$rsyncUser}@{$rsyncHost}::{$remotePrefix}{$fileToSync}";

            $cmd = 'RSYNC_PASSWORD=' . escapeshellarg($rsyncPassword)
                . ' rsync -avz'
                . ' --timeout=' . (int) $timeout
                . ' --port=' . (int) $rsyncPort . ' '
                . escapeshellarg($remote) . ' '
                . escapeshellarg($destinationPath . DIRECTORY_SEPARATOR) . ' 2>&1';

            $attempt = 0;
            $success = false;
            $lines   = [];
            $code    = 0;

            while ($attempt < $maxRetry) {
                $attempt++;
                @exec($cmd, $lines, $code);

                if ($code === 0) {
                    $success = true;
                    break;
                }

                // backoff ringan
                usleep(150000 * $attempt); // 150ms, 300ms, 450ms
            }

            $local = $destinationPath . DIRECTORY_SEPARATOR . $fileToSync;

            if ($success && is_file($local) && filesize($local) > 0) {
                @chmod($local, 0644);
                Log::info('Rsync OK', [
                    'file' => $fileToSync,
                    'size' => filesize($local),
                    'attempts' => $attempt,
                ]);
                return filesize($local);
            }

            // kalau gagal, log ringkas (tanpa password/cmd)
            if (!$success) {
                Log::warning('Rsync failed', [
                    'file' => $fileToSync,
                    'attempts' => $attempt,
                    'exit_code' => $code,
                    'last_output' => end($lines),
                ]);
            } elseif ($success && !is_file($local)) {
                Log::warning('Rsync reported success but file missing', ['expect' => $local]);
            }
        }

        return 0;
    }

    /**
     * Jika nama_file di DB tidak sesuai ekstensi lokal yang ada, perbaiki.
     * Return nama baru jika diperbaiki; null kalau tidak berubah.
     */
    protected function fixDbFilenameIfNeeded(string $original): ?string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $dir  = storage_path('app/public/lampiran');
        $candidates = glob($dir . DIRECTORY_SEPARATOR . $base . '.*');
        if (!$candidates) return null;

        // Ambil salah satu yang benar-benar ada > 0 bytes
        foreach ($candidates as $fullpath) {
            if (is_file($fullpath) && filesize($fullpath) > 0) {
                return basename($fullpath);
            }
        }
        return null;
    }

    /* ======================
     *  Payload aggregation
     * ====================== */
    protected function updatePayload($dataLampiran, ApiRequestPayloadLog $payload): void
    {
        $current = $payload->payload;
        $arr = [];
        if ($current) {
            $decoded = json_decode($current, true);
            if (is_array($decoded)) $arr = $decoded;
        }
        $arr[] = $dataLampiran;
        $payload->update(['payload' => json_encode($arr)]);
    }
}
