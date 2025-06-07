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

    public function __construct($list, $endpoint,$totalData, $userAgent)
    {
        $this->list = $list;
        $this->endpoint = $endpoint;
        $this->totalData = $totalData;
        $this->userAgent = $userAgent;
    }

    public function handle()
    {
        try {
            $totalTarget = 0;
            $allFetchedData = [];
            $totalSumber = 0;
            $totalData = $this->totalData;
            $apiRequestLog = $this->createApiRequestLog($totalData);
            $payload = $this->initializePayload($apiRequestLog);


            // Fetch data from the API for each item in the list
            foreach ($this->list as $ls) {
                $apiController = new ApiController();
                $url_request = $this->endpoint . '?id_biaya=' . $ls->id;
                $response = $this->fetchData($url_request);

                // Make sure response data is an array
                $dataLampiran = $response['data'] ?? [];

                // Increment totalSumber with each request, even if the data is empty

                if (!empty($dataLampiran)) {
                    $this->processFetchedData($dataLampiran, $payload);
                    // Debug to confirm increment
                    // \Log::info('Incremented totalSumber: ' . $totalSumber);


                } else {
                    $response = 'data tidak tersedia';
                    // \Log::info('No data available, incremented totalSumber: ' . $totalSumber);
                    $this->updatePayload($response, $payload);
                }
                $totalSumber++;
                $this->updateApiRequestLogProgress($apiRequestLog, $totalSumber, $totalData, $payload);
            }

            // $this->updateApiRequestLog($apiRequestLog, $totalTarget, $allFetchedData);

            // foreach ($allFetchedData as $data) {
            //     $this->processFetchedData($data, $payload);
            //     $totalSumber++;
            //     $this->updateApiRequestLogProgress($apiRequestLog, $totalSumber, $totalTarget, $payload);
            // }


        } catch (\Exception $e) {

            throw $e; // Optionally rethrow the exception
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
        // Mengupdate atau membuat entri baru di VerifikasiBiayaRutinDetailLampiran
        $verifikasi = VerifikasiBiayaRutinDetailLampiran::updateOrCreate(
            [
                'id' => $dataLampiran['id'],
            ],
            [
                'nama_file' => $dataLampiran['nama_file'],
                'verifikasi_biaya_rutin_detail' => $dataLampiran['id'],
            ]
        );

        // Cek nama file untuk ekstensi yang benar
        if (!empty($dataLampiran['nama_file'])) {
            // Ambil ukuran file setelah rsync
            $fileSize = $this->syncFile($dataLampiran['nama_file']);

            // Jika ukuran file lebih besar dari 0, berarti file berhasil disinkronkan
            if ($fileSize > 0) {
                // Memperbarui nama_file di VerifikasiBiayaRutinDetailLampiran jika ekstensi file tidak valid
                $fileInfo = pathinfo($dataLampiran['nama_file']);
                $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : '';

                // Jika ekstensi tidak valid, perbarui nama file dengan ekstensi yang benar
                $allowedExtensions = [
                    "pdf", "PDF", "doc", "DOC", "xls", "XLS", "xlsx", "XLSX",
                    "jpg", "JPG", "jpeg", "JPEG", "gif", "GIF", "png", "PNG", "rar", "zip", "ZIP"
                ];

                if (!in_array($extension, $allowedExtensions)) {
                    // Ubah nama file dengan ekstensi yang valid
                    foreach ($allowedExtensions as $ext) {
                        $newFileName = $fileInfo['filename'] . '.' . $ext;
                        $verifikasi->update(['nama_file' => $newFileName]);
                        break; // Hentikan setelah update dengan ekstensi pertama yang valid
                    }
                }
            }

            // Menyimpan ukuran file ke dalam dataLampiran
            $dataLampiran['size'] = $fileSize;
        }
        if($fileSize == 0){
            $payload->payload = 'file tidak tersedia';
        }

        // Memperbarui payload
        $this->updatePayload($dataLampiran, $payload);
    }

    protected function syncFile($namaFile)
    {
        $allowedExtensions = [
            "pdf", "PDF", "doc", "DOC", "xls", "XLS", "xlsx", "XLSX",
            "jpg", "JPG", "jpeg", "JPEG", "gif", "GIF", "png", "PNG", "rar", "zip", "ZIP"
        ];

        // Memisahkan nama file dan ekstensi
        $fileInfo = pathinfo($namaFile);
        $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : '';

        $destinationPath = storage_path('/app/public/lampiran');

        // Jika ekstensi tidak valid, coba dengan semua ekstensi yang diizinkan
        if (!in_array($extension, $allowedExtensions)) {
            foreach ($allowedExtensions as $ext) {
                $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --delete rsync://kominfo2@103.123.39.227:/lpu/{$fileInfo['filename']}.{$ext} {$destinationPath} 2>&1";
                shell_exec($rsyncCommand);

                // Cek ukuran file setelah rsync
                $filePath = "{$destinationPath}/{$fileInfo['filename']}.{$ext}";
                if (file_exists($filePath)) {
                    return filesize($filePath); // Return file size if found
                }
            }
            return 0; // Return 0 if no size found for any extension
        }

        // Jika ekstensi valid, lakukan rsync langsung
        $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --delete rsync://kominfo2@103.123.39.227:/lpu/{$namaFile} {$destinationPath} 2>&1";
        shell_exec($rsyncCommand);

        // Cek ukuran file setelah rsync
        $filePath = "{$destinationPath}/{$namaFile}";
        if (file_exists($filePath)) {
            return filesize($filePath); // Return file size if found
        }

        return 0; // Return 0 if no size found
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
