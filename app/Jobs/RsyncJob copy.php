<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\VerifikasiBiayaRutinDetailLampiran;

class RsyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $list;
    protected $endpoint;
    protected $userAgent;
    protected $totalData;

    public function __construct($list, $endpoint, $totalData, $userAgent)
    {
        $this->list = $list;
        $this->endpoint = $endpoint;
        $this->totalData = $totalData;
        $this->userAgent = $userAgent;
    }


    public function handle()
    {
        try {
            \Log::info('RsyncJob started.', ['total_data' => $this->totalData, 'endpoint' => $this->endpoint]);

            if ($this->totalData == 0) {
                \Log::warning('RsyncJob stopped: totalData is zero.');
                $this->createApiRequestLog($this->totalData); 
                return; 
            }

            $totalSumber = 0;
            $apiRequestLog = $this->createApiRequestLog($this->totalData);
            $payload = $this->initializePayload($apiRequestLog);

            foreach ($this->list as $ls) {
                $url_request = $this->endpoint . '?id_biaya=' . $ls->id;
                \Log::info('Fetching data for URL: ' . $url_request);

                $response = $this->fetchData($url_request);
                \Log::info('Raw API Response: ' . json_encode($response));

                $dataLampiran = $response['data'] ?? [];

                if (!empty($dataLampiran)) {
                    \Log::info('Data found, processing...', ['id' => $ls->id]);
                    $this->processFetchedData($dataLampiran, $payload);
                } else {
                    \Log::warning('Data not available from API for ID: ' . $ls->id);
                    $this->updatePayload('data tidak tersedia', $payload);
                }

                $totalSumber++;
                $this->updateApiRequestLogProgress($apiRequestLog, $totalSumber, $this->totalData, $payload);
            }

            \Log::info('RsyncJob finished successfully.');
        } catch (\Exception $e) {
            \Log::error('An exception occurred in RsyncJob: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

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
    protected function fetchData($url_request)
    {
        $apiController = new ApiController();
        $request = request();
        $request->merge(['end_point' => $url_request]);
        return $apiController->makeRequest($request);
    }


    protected function processFetchedData($dataLampiran, $payload)
    {
        $verifikasi = VerifikasiBiayaRutinDetailLampiran::updateOrCreate(
            [
                'id' => $dataLampiran['id'],
            ],
            [
                'nama_file' => $dataLampiran['nama_file'],
                'verifikasi_biaya_rutin_detail' => $dataLampiran['id'],
            ]
        );

        if (!empty($dataLampiran['nama_file'])) {
            $fileSize = $this->syncFile($dataLampiran['nama_file']);

            if ($fileSize > 0) {
                $fileInfo = pathinfo($dataLampiran['nama_file']);
                $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : '';

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

                if (!in_array($extension, $allowedExtensions)) {
                    foreach ($allowedExtensions as $ext) {
                        $newFileName = $fileInfo['filename'] . '.' . $ext;
                        $verifikasi->update(['nama_file' => $newFileName]);
                        break;
                    }
                }
            }

            $dataLampiran['size'] = $fileSize;
        }
        if ($fileSize == 0) {
            $payload->payload = 'file tidak tersedia';
        }

        // Memperbarui payload
        $this->updatePayload($dataLampiran, $payload);
    }

    protected function syncFile($namaFile)
    {
        $destinationPath = storage_path('/app/public/lampiran');
        $fileInfo = pathinfo($namaFile);
        $filenameWithoutExt = $fileInfo['filename'];

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

        $currentExtension = isset($fileInfo['extension']) ? $fileInfo['extension'] : '';

        if (!empty($currentExtension) && in_array($currentExtension, $allowedExtensions)) {
            array_unshift($allowedExtensions, $currentExtension);
            $allowedExtensions = array_unique($allowedExtensions);
        }

        foreach ($allowedExtensions as $ext) {
            $fileToSync = "{$filenameWithoutExt}.{$ext}";
            \Log::info("file name:" . $fileToSync);
            // $rsyncCommand = "export RSYNC_PASSWORD='r4has1ia_dev' && rsync -arvz --delete rsync://kominfo2@149.129.221.192::lpu/{$fileToSync} {$destinationPath} 2>&1";
            $rsyncCommand = "export RSYNC_PASSWORD='r4has1ia_dev' && rsync -avz --port=8731 kominfo2@149.129.221.192::lpu/{$fileToSync} {$destinationPath} 2>&1";

            \Log::info('Executing Rsync Command: ' . $rsyncCommand);

            $output = shell_exec($rsyncCommand);

            \Log::info('Rsync Output: ' . $output);

            $localFilePath = "{$destinationPath}/{$fileToSync}";

            // LOG 8: Catat path file lokal yang sedang diperiksa
            \Log::info('Checking for local file at: ' . $localFilePath);

            if (file_exists($localFilePath) && filesize($localFilePath) > 0) {
                \Log::info('File synced successfully!', ['file' => $localFilePath, 'size' => filesize($localFilePath)]);
                return filesize($localFilePath); // Kembalikan ukuran file jika berhasil
            }
        }

        \Log::warning('Rsync failed for all attempted extensions.', ['original_file' => $namaFile]);
        return 0; // Kembalikan 0 jika tidak ada file yang berhasil disinkronkan
    }


    protected function updatePayload($dataLampiran, $payload)
    {
        $updated_payload = $payload->payload ?? '';
        // $jsonData = json_encode($dataLampiran);
        // $fileSize = strlen($jsonData);
        // $dataLampiran['size'] = $fileSize;

        if ($updated_payload !== '' || $payload->payload !== null) {
            $existing_payload = json_decode($updated_payload, true);
            $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];
            $existing_payload[] = (object) $dataLampiran;
            $updated_payload = json_encode($existing_payload);
        } else {
            $updated_payload = json_encode([(object) $dataLampiran]);
        }

        $payload->update(['payload' => $updated_payload]);
    }

    protected function updateApiRequestLogProgress($apiRequestLog, $totalSumber, $totalData, $payload)
    {
        $status = ($totalSumber == $totalData) ? 'success' : 'on progress';
        $apiRequestLog->update([
            'successful_records' => $totalSumber,
            'status' => $status,
        ]);
    }
}
