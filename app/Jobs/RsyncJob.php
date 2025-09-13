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
    protected $endpoint;
    protected $userAgent;
    protected $totalData;

    /**
     * @param \Illuminate\Support\Collection|array $list   daftar objek yang minimal punya properti id (id_biaya)
     * @param string $endpoint                        endpoint API POS (tanpa query string)
     * @param int $totalData                          total item sumber (untuk log progres)
     * @param string $userAgent                       UA yang dipakai untuk pencatatan
     */
    public function __construct($list, $endpoint, $totalData, $userAgent)
    {
        $this->list = $list;
        $this->endpoint = $endpoint;
        $this->totalData = (int) $totalData;
        $this->userAgent = (string) $userAgent;
    }

    public function handle()
    {
        try {
            Log::info('RsyncJob started.', ['total_data' => $this->totalData, 'endpoint' => $this->endpoint]);

            if ($this->totalData === 0) {
                Log::warning('RsyncJob stopped: totalData is zero.');
                $this->createApiRequestLog($this->totalData);
                return;
            }

            $totalSumber = 0;
            $apiRequestLog = $this->createApiRequestLog($this->totalData);
            $payload = $this->initializePayload($apiRequestLog);

            foreach ($this->list as $ls) {
                $url_request = $this->endpoint . '?id_biaya=' . $ls->id;
                Log::info('Fetching data for URL: ' . $url_request);

                $response = $this->fetchData($url_request);
                Log::info('Raw API Response: ' . json_encode($response));

                $dataLampiran = $response['data'] ?? [];

                if (!empty($dataLampiran)) {
                    Log::info('Data found, processing...', ['id_biaya' => $ls->id, 'lampiran_id' => $dataLampiran['id'] ?? null]);
                    $this->processFetchedData($dataLampiran, $payload);
                } else {
                    Log::warning('Data not available from API for ID: ' . $ls->id);
                    $this->updatePayload(['error' => 'data tidak tersedia', 'id_biaya' => $ls->id], $payload);
                }

                $totalSumber++;
                $this->updateApiRequestLogProgress($apiRequestLog, $totalSumber, $this->totalData, $payload);
            }

            Log::info('RsyncJob finished successfully.');
        } catch (Exception $e) {
            Log::error('An exception occurred in RsyncJob: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /** =========================
     *  Helpers: API Request Log
     *  ========================= */
    protected function createApiRequestLog($totalData)
    {
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        $agent->setUserAgent($this->userAgent);
        $platformRequest = $agent->platform() . '/' . $agent->browser();
        $status = $totalData == 0 ? 'data tidak tersedia' : 'on progress';

        return ApiRequestLog::create([
            'komponen' => 'Lampiran Biaya',
            'tanggal' => now(),
            'ip_address' => $serverIpAddress,
            'platform_request' => $platformRequest,
            'successful_records' => 0,
            'available_records' => $totalData,
            'total_records' => $totalData,
            'status' => $status,
        ]);
    }

    protected function initializePayload($apiRequestLog)
    {
        return ApiRequestPayloadLog::create([
            'api_request_log_id' => $apiRequestLog->id,
            'payload' => null,
        ]);
    }

    protected function updateApiRequestLog($apiRequestLog, $totalTarget, $dataLampiran)
    {
        $status = empty($dataLampiran) ? 'data tidak tersedia' : 'on progress';

        $apiRequestLog->update([
            'total_records' => $totalTarget,
            'available_records' => $totalTarget,
            'status' => $status,
        ]);
    }

    /** =====================
     *  Helpers: Fetch Data
     *  ===================== */
    protected function fetchData($url_request)
    {
        $apiController = new ApiController();
        $request = request();
        $request->merge(['end_point' => $url_request]);

        // makeRequest diharapkan mengembalikan array dengan key: success, message, total_data, data
        return $apiController->makeRequest($request);
    }

    /** =========================
     *  Process & Sync Lampiran
     *  ========================= */
    protected function processFetchedData($dataLampiran, $payload)
    {
        $fileSize = 0; // pastikan selalu terdefinisi

        // Simpan/Update meta lampiran ke DB
        $verifikasi = VerifikasiBiayaRutinDetailLampiran::updateOrCreate(
            [
                'id' => $dataLampiran['id'],
            ],
            [
                'nama_file' => $dataLampiran['nama_file'],
                // Pastikan field ini memang FK ke detail; sesuaikan bila berbeda.
                'verifikasi_biaya_rutin_detail' => $dataLampiran['id'],
            ]
        );

        // Sync file jika ada nama_file
        if (!empty($dataLampiran['nama_file'])) {
            $fileSize = $this->syncFile($dataLampiran['nama_file']);

            // Jika ekstensi awal tak valid, update nama_file ke ekstensi yang benar (hanya jika file lokalnya memang ada)
            if ($fileSize > 0) {
                $fileInfo = pathinfo($dataLampiran['nama_file']);
                $currentExt = $fileInfo['extension'] ?? '';
                $allowed = [
                    "pdf",
                    "PDF",
                    "doc",
                    "DOC",
                    "xls",
                    "XLS",
                    "xlsx",
                    "XLSX",
                    "jpg",
                    "JPG",
                    "jpeg",
                    "JPEG",
                    "gif",
                    "GIF",
                    "png",
                    "PNG",
                    "rar",
                    "zip",
                    "ZIP"
                ];

                if (!in_array($currentExt, $allowed, true)) {
                    foreach ($allowed as $ext) {
                        $try = ($fileInfo['filename'] ?? $dataLampiran['nama_file']) . '.' . $ext;
                        $local = storage_path('app/public/lampiran/' . $try);
                        if (is_file($local) && filesize($local) > 0) {
                            $verifikasi->update(['nama_file' => $try]);
                            $dataLampiran['nama_file'] = $try;
                            break;
                        }
                    }
                }
            }

            $dataLampiran['size'] = $fileSize;
        }

        if ($fileSize === 0) {
            $this->updatePayload([
                'error' => 'file tidak tersedia',
                'nama_file' => $dataLampiran['nama_file'] ?? null,
                'id' => $dataLampiran['id'] ?? null,
                'id_biaya' => $dataLampiran['id_biaya'] ?? null,
            ], $payload);
        } else {
            $this->updatePayload($dataLampiran, $payload);
        }
    }

    protected function syncFile($namaFile)
    {
        // Pastikan folder tujuan ada
        $destinationPath = storage_path('app/public/lampiran'); // tanpa leading slash
        if (!is_dir($destinationPath)) {
            Storage::makeDirectory('public/lampiran'); // otomatis buat storage/app/public/lampiran
        }

        // Baca konfigurasi dari ENV agar fleksibel
        $rsyncHost     = '147.139.133.1';
        $rsyncPort     = 8732;
        $rsyncUser     = 'kominfo2';
        $rsyncPassword = 'k0minf0!';
        $rsyncModule   = 'lpu';

        // Cek rsync binary tersedia
        $linesCheck = [];
        $codeCheck = 0;
        exec('command -v rsync || which rsync', $linesCheck, $codeCheck);
        if ($codeCheck !== 0 || empty($linesCheck)) {
            Log::error('Rsync binary not found. Install rsync terlebih dahulu.');
            return 0;
        }

        $fileInfo = pathinfo($namaFile);
        $filenameWithoutExt = $fileInfo['filename'] ?? $namaFile;
        $currentExtension = $fileInfo['extension'] ?? '';

        $allowedExtensions = [
            "pdf",
            "PDF",
            "doc",
            "DOC",
            "xls",
            "XLS",
            "xlsx",
            "XLSX",
            "jpg",
            "JPG",
            "jpeg",
            "JPEG",
            "gif",
            "GIF",
            "png",
            "PNG",
            "rar",
            "zip",
            "ZIP"
        ];

        if ($currentExtension && in_array($currentExtension, $allowedExtensions, true)) {
            array_unshift($allowedExtensions, $currentExtension);
            $allowedExtensions = array_values(array_unique($allowedExtensions));
        }

        foreach ($allowedExtensions as $ext) {
            $fileToSync = "{$filenameWithoutExt}.{$ext}";
            $remote = "{$rsyncUser}@{$rsyncHost}::{$rsyncModule}/{$fileToSync}";

            // Gunakan exec agar dapat exit code
            $cmd = 'RSYNC_PASSWORD=' . escapeshellarg($rsyncPassword)
                . ' rsync -avz --port=' . (int) $rsyncPort . ' '
                . escapeshellarg($remote) . ' '
                . escapeshellarg($destinationPath . DIRECTORY_SEPARATOR) . ' 2>&1';

            Log::info('Trying rsync', ['file' => $fileToSync, 'cmd' => $cmd]);

            $lines = [];
            $code  = 0;
            exec($cmd, $lines, $code);

            Log::info('Rsync result', ['file' => $fileToSync, 'exit_code' => $code, 'output' => $lines]);

            $local = $destinationPath . DIRECTORY_SEPARATOR . $fileToSync;

            if (is_file($local) && filesize($local) > 0) {
                @chmod($local, 0644);
                Log::info('File synced successfully!', ['file' => $local, 'size' => filesize($local)]);
                return filesize($local);
            }

            if ($code === 0 && !is_file($local)) {
                Log::warning('Rsync reported success but file not found locally.', ['expected' => $local]);
            }
        }

        Log::warning('Rsync failed for all attempted extensions.', ['original_file' => $namaFile]);
        return 0;
    }

    /** ======================
     *  Payload Aggregation
     *  ====================== */
    protected function updatePayload($dataLampiran, $payload)
    {
        $updated_payload = $payload->payload ?? '';

        if ($updated_payload !== '' && $payload->payload !== null) {
            $existing_payload = json_decode($updated_payload, true);
            $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];
            $existing_payload[] = (object) $dataLampiran;
            $updated_payload = json_encode($existing_payload);
        } else {
            $updated_payload = json_encode([(object) $dataLampiran]);
        }

        $payload->update(['payload' => $updated_payload]);
    }

    /** ======================
     *  Progress Logging
     *  ====================== */
    protected function updateApiRequestLogProgress($apiRequestLog, $totalSumber, $totalData, $payload)
    {
        $status = ($totalSumber == $totalData) ? 'success' : 'on progress';
        $apiRequestLog->update([
            'successful_records' => $totalSumber,
            'status' => $status,
        ]);
    }
}
