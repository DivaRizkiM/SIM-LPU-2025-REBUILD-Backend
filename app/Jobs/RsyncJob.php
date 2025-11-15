<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\VerifikasiBiayaRutinDetailLampiran;
use App\Models\Npp;
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

    protected int $processedCount = 0;
    protected int $downloadedCount = 0;
    protected int $apiEmptyCount = 0;
    protected int $remoteMissingCount = 0;
    protected int $errorCount = 0;
    protected int $skippedCount = 0; // sudah ada & valid

    public $timeout = 3600; // 1 jam

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
            Log::info('RsyncJob started.', [
                'total_data' => $this->totalData, 
                'endpoint' => $this->endpoint
            ]);

            if ($this->totalData === 0) {
                Log::warning('RsyncJob stopped: totalData is zero.');
                $this->createApiRequestLog(0);
                return;
            }

            $apiRequestLog = $this->createApiRequestLog($this->totalData);
            $payload = $this->initializePayload($apiRequestLog);

            Storage::makeDirectory('public/lampiran');

            foreach ($this->list as $item) {
                $this->processedCount++;

                try {
                    // Tentukan source type
                    $sourceType = $item->source_type ?? 'verifikasi';

                    if ($sourceType === 'npp') {
                        $this->processNppItem($item, $payload);
                    } else {
                        $this->processVerifikasiItem($item, $payload);
                    }
                } catch (\Exception $e) {
                    $this->errorCount++;
                    Log::error('Error processing item', [
                        'id' => $item->id,
                        'type' => $sourceType ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    
                    $this->updatePayload([
                        'error' => $e->getMessage(),
                        'id' => $item->id,
                        'type' => $sourceType ?? 'unknown',
                    ], $payload);
                }

                $this->updateApiRequestLogProgress($apiRequestLog, $payload);
            }

            $this->finalizeApiRequestLog($apiRequestLog, $payload);

            Log::info('RsyncJob finished.', [
                'processed' => $this->processedCount,
                'downloaded' => $this->downloadedCount,
                'skipped' => $this->skippedCount,
                'api_empty' => $this->apiEmptyCount,
                'remote_missing' => $this->remoteMissingCount,
                'errors' => $this->errorCount,
            ]);
        } catch (Exception $e) {
            Log::error('RsyncJob exception: ' . $e->getMessage(), [
                'file' => $e->getFile(), 
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    protected function processNppItem($item, $payload): void
    {
        $npp = Npp::find($item->id);
        
        if (!$npp || empty($npp->nama_file)) {
            $this->apiEmptyCount++;
            $this->updatePayload([
                'error' => 'NPP tidak ditemukan atau nama_file kosong',
                'id' => $item->id,
                'type' => 'npp',
            ], $payload);
            return;
        }

        $namaFile = trim($npp->nama_file);
        
        // Cek apakah file sudah ada dan valid
        $localPath = storage_path('app/public/lampiran/' . $namaFile);
        if (is_file($localPath) && filesize($localPath) > 0) {
            $this->skippedCount++;
            Log::info('File already exists, skipped', ['file' => $namaFile]);
            return;
        }

        // Sync file - return array dengan size dan filename
        $result = $this->syncFileSmart($namaFile);
        
        if ($result['size'] > 0) {
            $this->downloadedCount++;
            
            // Jika nama file berbeda (ada ekstensi baru), update DB
            if ($result['filename'] && $result['filename'] !== $namaFile) {
                $npp->update(['nama_file' => $result['filename']]);
                Log::info('NPP Filename updated in DB', [
                    'npp_id' => $npp->id,
                    'original' => $namaFile,
                    'updated' => $result['filename']
                ]);
            }
            
            $this->updatePayload([
                'id' => $npp->id,
                'type' => 'npp',
                'nama_file' => $result['filename'] ?? $namaFile,
                'size' => $result['size'],
                'status' => 'success'
            ], $payload);
        } else {
            $this->remoteMissingCount++;
            $this->updatePayload([
                'error' => 'File tidak ditemukan/0 bytes setelah rsync',
                'id' => $npp->id,
                'type' => 'npp',
                'nama_file' => $namaFile,
            ], $payload);
        }
    }

    protected function processVerifikasiItem($item, $payload): void
    {
        // Ambil data lampiran dari tabel verifikasi_biaya_rutin_detail_lampiran
        $lampiranData = VerifikasiBiayaRutinDetailLampiran::where(
            'verifikasi_biaya_rutin_detail', 
            $item->id
        )->first();

        if (!$lampiranData || empty($lampiranData->nama_file)) {
            $this->apiEmptyCount++;
            $this->updatePayload([
                'error' => 'Data lampiran tidak ditemukan di tabel verifikasi_biaya_rutin_detail_lampiran',
                'id_biaya' => $item->id,
                'type' => 'verifikasi',
            ], $payload);
            return;
        }

        $namaFile = trim($lampiranData->nama_file);
        
        // Cek apakah file sudah ada dan valid
        $localPath = storage_path('app/public/lampiran/' . $namaFile);
        if (is_file($localPath) && filesize($localPath) > 0) {
            $this->skippedCount++;
            Log::info('File already exists, skipped', [
                'file' => $namaFile,
                'id_biaya' => $item->id
            ]);
            return;
        }

        // Sync file - return array dengan size dan filename
        $result = $this->syncFileSmart($namaFile);
        
        if ($result['size'] > 0) {
            $this->downloadedCount++;
            
            // Jika nama file berbeda (ada ekstensi baru), update DB
            if ($result['filename'] && $result['filename'] !== $namaFile) {
                $lampiranData->update(['nama_file' => $result['filename']]);
                Log::info('Verifikasi Filename updated in DB', [
                    'lampiran_id' => $lampiranData->id,
                    'id_biaya' => $item->id,
                    'original' => $namaFile,
                    'updated' => $result['filename']
                ]);
            }
            
            $this->updatePayload([
                'id' => $lampiranData->id,
                'id_biaya' => $item->id,
                'type' => 'verifikasi',
                'nama_file' => $result['filename'] ?? $namaFile,
                'size' => $result['size'],
                'status' => 'success'
            ], $payload);
        } else {
            $this->remoteMissingCount++;
            $this->updatePayload([
                'error' => 'File tidak ditemukan/0 bytes setelah rsync',
                'id' => $lampiranData->id,
                'id_biaya' => $item->id,
                'type' => 'verifikasi',
                'nama_file' => $namaFile,
            ], $payload);
        }
    }

    protected function createApiRequestLog(int $totalData): ApiRequestLog
    {
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        $agent->setUserAgent($this->userAgent);
        $platformRequest = $agent->platform() . '/' . $agent->browser();

        return ApiRequestLog::create([
            'komponen' => 'Lampiran Biaya',
            'tanggal' => now(),
            'ip_address' => $serverIpAddress,
            'platform_request' => $platformRequest,
            'successful_records' => 0,
            'available_records' => $totalData,
            'total_records' => $totalData,
            'status' => 'on progress',
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
            'successful_records' => $this->downloadedCount,
            'status' => $status,
        ]);
    }

    protected function finalizeApiRequestLog(ApiRequestLog $apiRequestLog, ApiRequestPayloadLog $payload): void
    {
        $summary = [
            'processed' => $this->processedCount,
            'downloaded' => $this->downloadedCount,
            'skipped' => $this->skippedCount,
            'api_empty' => $this->apiEmptyCount,
            'remote_missing' => $this->remoteMissingCount,
            'errors' => $this->errorCount,
        ];
        $this->updatePayload(['summary' => $summary], $payload);

        $apiRequestLog->update([
            'successful_records' => $this->downloadedCount,
            'status' => 'success',
        ]);
    }

    protected function fetchData(string $url_request): array
    {
        $apiController = new ApiController();
        $request = request();
        $request->merge(['end_point' => $url_request]);

        $res = $apiController->makeRequest($request);
        return is_array($res) ? $res : [];
    }

    protected function syncFileSmart(string $namaFile): array
    {
        $destinationPath = storage_path('app/public/lampiran');
        
        if (!is_dir($destinationPath)) {
            Storage::makeDirectory('public/lampiran');
        }

        // Konfigurasi rsync dari ENV atau hardcode (SESUAIKAN PORT!)
        $rsyncHost = env('RSYNC_HOST', '147.139.133.1');
        $rsyncPort = env('RSYNC_PORT', 8732); // ✅ PERBAIKAN: Port 8732 seperti SSH
        $rsyncUser = env('RSYNC_USER', 'kominfo2');
        $rsyncPassword = env('RSYNC_PASSWORD', 'k0minf0!');
        $rsyncModule = env('RSYNC_MODULE', 'lpu');
        $timeout = 20;
        $maxRetry = 3;

        // Cek rsync binary
        $linesCheck = [];
        $codeCheck = 0;
        @exec('command -v rsync || which rsync 2>/dev/null', $linesCheck, $codeCheck);
        
        if ($codeCheck !== 0 || empty($linesCheck)) {
            Log::error('Rsync binary not found.');
            $this->errorCount++;
            return ['size' => 0, 'filename' => null];
        }

        // ✅ PERBAIKAN: Validasi nama file lebih ketat
        $namaFile = trim($namaFile);
        if ($namaFile === '' || 
            preg_match('/[\x00-\x1F\x7F]/', $namaFile) ||
            strlen($namaFile) < 3 || // Minimal 3 karakter
            strpos($namaFile, '_') === 0) { // Tidak boleh dimulai dengan underscore saja
            Log::warning('Invalid filename pattern', ['nama_file' => $namaFile]);
            return ['size' => 0, 'filename' => null];
        }

        $fileInfo = pathinfo($namaFile);
        $filenameWithoutExt = $fileInfo['filename'] ?? $namaFile;
        $currentExt = $fileInfo['extension'] ?? '';

        // Kandidat ekstensi
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
        $candidates = [];

        if ($currentExt !== '') {
            $candidates[] = $filenameWithoutExt . '.' . $currentExt;
            $candidates[] = $filenameWithoutExt . '.' . strtolower($currentExt);
            $candidates[] = $filenameWithoutExt . '.' . strtoupper($currentExt);
        } else {
            foreach ($allowed as $ext) {
                $candidates[] = $filenameWithoutExt . '.' . $ext;
            }
        }

        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $fileToSync) {
            // ✅ PERBAIKAN: Tambahkan pre-check dengan rsync --dry-run
            $remote = "{$rsyncUser}@{$rsyncHost}::{$rsyncModule}/{$fileToSync}";
            
            // Check jika file ada di remote (dry-run)
            $checkCmd = 'RSYNC_PASSWORD=' . escapeshellarg($rsyncPassword)
                . ' rsync -n' // dry-run
                . ' --port=' . (int) $rsyncPort
                . ' --timeout=10'
                . ' ' . escapeshellarg($remote)
                . ' /dev/null 2>&1';
            
            $checkLines = [];
            $checkCode = 0;
            @exec($checkCmd, $checkLines, $checkCode);
            
            // Jika file tidak ada di remote, skip
            if ($checkCode !== 0) {
                Log::debug('File not found in remote', [
                    'file' => $fileToSync,
                    'exit_code' => $checkCode
                ]);
                continue;
            }

            // ✅ PERBAIKAN: Command rsync sesuai SSH script
            $cmd = 'RSYNC_PASSWORD=' . escapeshellarg($rsyncPassword)
                . ' rsync -avz'
                . ' --timeout=' . (int) $timeout
                . ' --port=' . (int) $rsyncPort
                . ' --no-perms --omit-dir-times' // Sesuai SSH
                . ' ' . escapeshellarg($remote)
                . ' ' . escapeshellarg($destinationPath . DIRECTORY_SEPARATOR)
                . ' 2>&1';

            $attempt = 0;
            $success = false;
            $lines = [];
            $code = 0;

            while ($attempt < $maxRetry) {
                $attempt++;
                $lines = []; // Reset output
                @exec($cmd, $lines, $code);

                if ($code === 0) {
                    $success = true;
                    break;
                }

                usleep(150000 * $attempt);
            }

            $local = $destinationPath . DIRECTORY_SEPARATOR . $fileToSync;

            if ($success && is_file($local) && filesize($local) > 0) {
                @chmod($local, 0644);
                Log::info('Rsync OK', [
                    'file' => $fileToSync,
                    'size' => filesize($local),
                    'attempts' => $attempt,
                ]);
                return ['size' => filesize($local), 'filename' => $fileToSync];
            }

            if (!$success) {
                Log::warning('Rsync failed', [
                    'file' => $fileToSync,
                    'attempts' => $attempt,
                    'exit_code' => $code,
                    'output' => implode("\n", array_slice($lines, -5)) // Last 5 lines
                ]);
            }
        }

        Log::error('All rsync candidates failed', [
            'original_file' => $namaFile,
            'tried' => $candidates
        ]);
        
        return ['size' => 0, 'filename' => null];
    }

    protected function fixDbFilenameIfNeeded(string $original): ?string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $dir = storage_path('app/public/lampiran');
        $candidates = glob($dir . DIRECTORY_SEPARATOR . $base . '.*');
        
        if (!$candidates) {
            return null;
        }

        foreach ($candidates as $fullpath) {
            if (is_file($fullpath) && filesize($fullpath) > 0) {
                return basename($fullpath);
            }
        }
        
        return null;
    }

    protected function updatePayload($data, ApiRequestPayloadLog $payload): void
    {
        $current = $payload->payload;
        $arr = [];
        
        if ($current) {
            $decoded = json_decode($current, true);
            if (is_array($decoded)) {
                $arr = $decoded;
            }
        }
        
        $arr[] = $data;
        $payload->update(['payload' => json_encode($arr)]);
    }
}
