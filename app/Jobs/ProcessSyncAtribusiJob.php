<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiLog;
use App\Models\BiayaAtribusi;
use App\Models\BiayaAtribusiDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;

class ProcessSyncAtribusiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $list;
    protected $totalItems;
    protected $id_regional;
    protected $id_kprk;
    protected $bulan;
    protected $tahun;
    protected $userAgent;
    protected $endpoint;

    public function __construct($list, $totalItems, $id_regional, $id_kprk, $bulan, $endpoint, $tahun, $userAgent)
    {
        $this->list = $list;
        $this->totalItems = $totalItems;
        $this->id_regional = $id_regional;
        $this->id_kprk = $id_kprk;
        $this->bulan = $bulan;
        $this->tahun = $tahun;
        $this->userAgent = $userAgent;
        $this->endpoint = $endpoint;
    }

    public function handle()
    {
        try {
            $endpoints = [
                'biaya_upl',
                'biaya_angkutan_pos_setempat',
                'biaya_sopir_tersier'
            ];

            $apiRequestLog = $this->createApiRequestLog();
            $payload = $this->initializePayload($apiRequestLog);

            $totalTarget = 0;
            $totalSumber = 0;
            $allFetchedData = [];

            // Tentukan endpoint yang akan digunakan
            $endpointsToUse = empty($this->endpoint) ? $endpoints : [$this->endpoint];

            foreach ($endpointsToUse as $ep) {
                foreach ($this->list as $ls) {
                    $url_request = $ep . '?bulan=' . $this->bulan . '&id_kprk=' . $ls->id . '&tahun=' . $this->tahun;
                    $response = $this->fetchData($url_request);

                    if (!isset($response['data'])) {
                        continue; // Lewati jika ada kesalahan
                    }

                    $dataBiayaAtribusi = $response['data'] ?? [];
                    if (!empty($dataBiayaAtribusi)) {
                        foreach ($dataBiayaAtribusi as $data) {
                            $allFetchedData[] = $data;
                            $totalTarget++;
                        }
                    }
                }
            }

            // Memperbarui log permintaan API
            $this->updateApiRequestLog($apiRequestLog, $totalTarget, $allFetchedData);

            // Memproses data yang diambil
            foreach ($allFetchedData as $data) {
                $this->processFetchedData($data, $payload);
                $totalSumber++;
                $this->updateApiRequestLogProgress($apiRequestLog, $totalSumber, $totalTarget, $payload);
            }

        } catch (\Exception $e) {
            \Log::error('Error in ProcessSyncAtribusiJob', ['exception' => $e]);
            throw $e; // Mungkin melemparkan kembali pengecualian
        }
    }

    protected function fetchData($url_request)
    {
        $apiController = new ApiController();
        $request = request();
        $request->merge(['end_point' => $url_request]);
        return $apiController->makeRequest($request);
    }

    protected function createApiRequestLog()
    {
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        $agent->setUserAgent($this->userAgent);
        $platformRequest = $agent->platform() . '/' . $agent->browser();

        return ApiRequestLog::create([
            'komponen' => 'Biaya Atribusi',
            'tanggal' => now(),
            'ip_address' => $serverIpAddress,
            'platform_request' => $platformRequest,
            'successful_records' => 0,
            'available_records' => 0,
            'total_records' => 0,
            'status' => 'Memuat Data',
        ]);
    }

    protected function initializePayload($apiRequestLog)
    {
        return ApiRequestPayloadLog::create([
            'api_request_log_id' => $apiRequestLog->id,
            'payload' => null,
        ]);
    }

    protected function updateApiRequestLog($apiRequestLog, $totalTarget, $allFetchedData)
    {
        $status = empty($allFetchedData) ? 'data tidak tersedia' : 'on progress';

        $apiRequestLog->update([
            'total_records' => $totalTarget,
            'available_records' => $totalTarget,
            'status' => $status,
        ]);
    }

    protected function processFetchedData($data, $payload)
    {
        $idBiayaAtribusi = trim($data['id_kprk']) . trim($data['tahun_anggaran']) . trim($data['triwulan']);

        $biayaAtribusi = BiayaAtribusi::updateOrCreate(
            [
                'id' =>  $idBiayaAtribusi,
            ],
            [
                'tahun_anggaran' => $data['tahun_anggaran'],
                'triwulan' => $data['triwulan'],
                'id_kprk' => $data['id_kprk'],
                'id_regional' => $this->id_regional,
                'tgl_singkronisasi' => now(),
                'id_status' => 7,
                'id_status_kprk' => 7,
            ]
        );

        BiayaAtribusiDetail::updateOrCreate(
            [
                'id' => $data['id'],
            ],
            [
                'id_biaya_atribusi' =>  $idBiayaAtribusi,
                'id_rekening_biaya' => $data['koderekening'],
                'bulan' => $data['bulan'],
                'pelaporan' => $data['nominal'],
                'keterangan' => $data['keterangan'],
                'lampiran' => $data['lampiran'],
            ]
        );

        $this->updateBiayaAtribusiTotal($data);
        $data['size'] = 0;

        if (!empty($data['lampiran'])) {
            $namaFile = $data['lampiran'];
            $destinationPath = storage_path('/app/public/lampiran');
            $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --delete rsync://kominfo2@103.123.39.227:/lpu/{$namaFile} {$destinationPath} 2>&1";
            $output = shell_exec($rsyncCommand);
            if (preg_match('/total size is (\d+)/', $output, $matches)) {
                $size = (int)$matches[1];
            }
            $data['size'] = $size; // Menyimpan ukuran file
        }

        $this->updatePayload($data, $payload);
    }

    protected function updateBiayaAtribusiTotal($data)
    {
        $biayaAtribusiDetail = BiayaAtribusiDetail::select(DB::raw('SUM(pelaporan) as total_pelaporan'))
            ->where('id_biaya_atribusi', $data['id_kprk'] . $data['tahun_anggaran'] . $data['triwulan'])
            ->first();

        $biayaAtribusiTotal = BiayaAtribusi::where('id', $data['id_kprk'] . $data['tahun_anggaran'] . $data['triwulan'])
            ->first();

        $biayaAtribusiTotal->update([
            'total_biaya' => $biayaAtribusiDetail->total_pelaporan,
            'id_status' => 7,
            'id_status_kprk' => 7,
        ]);
    }

    protected function updatePayload($data, $payload)
    {
        $updated_payload = $payload->payload ?? '';
        $jsonData = json_encode($data);
        $fileSize = strlen($jsonData);
        $data['size'] = $fileSize;

        if ($updated_payload !== '' || $payload->payload !== null) {
            $existing_payload = json_decode($updated_payload, true);
            $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];
            $new_payload = (object) $data;
            $existing_payload[] = $new_payload;
            $updated_payload = json_encode($existing_payload);
        } else {
            $updated_payload = json_encode([(object) $data]);
        }

        $payload->update(['payload' => $updated_payload]);
    }

    protected function updateApiRequestLogProgress($apiRequestLog, $totalSumber, $totalTarget, $payload)
    {
        $status = ($totalSumber == $totalTarget) ? 'success' : 'on progress';

        $apiRequestLog->update([
            'successful_records' => $totalSumber,
            'status' => $status,
        ]);
    }
}
