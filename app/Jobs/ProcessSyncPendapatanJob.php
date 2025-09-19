<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\KategoriBiaya;
use App\Models\VerifikasiBiayaRutin;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\VerifikasiBiayaRutinDetailLampiran;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\KategoriPendapatan;
use App\Models\Pendapatan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class ProcessSyncPendapatanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $list;
    protected $totalItems;
    protected $endpoint;
    protected $id_regional;
    protected $id_kprk;
    protected $id_kpc;
    protected $triwulan;
    protected $tahun;
    protected $userAgent;

    public function __construct($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $triwulan, $tahun, $userAgent)
    {
        $this->list = $list;
        $this->totalItems = $totalItems;
        $this->endpoint = $endpoint;
        $this->id_regional = $id_regional;
        $this->id_kprk = $id_kprk;
        $this->id_kpc = $id_kpc;
        $this->triwulan = $triwulan;
        $this->tahun = $tahun;
        $this->userAgent = $userAgent;
    }

    public function handle()
    {
        $apiRequestLog = null;

        try {
            $apiRequestLog = $this->createApiRequestLog();
            $payload = $this->initializePayload($apiRequestLog);
            $totalTarget = 0;
            $totalSumber = 0;
            $allFetchedData = [];
            $kategori_pendapatan = KategoriPendapatan::get();
            foreach ($kategori_pendapatan as $kp) {
                $kpid = str_pad($kp->id, 2, '0', STR_PAD_LEFT);
                foreach ($this->list as $ls) {
                    $apiController = new ApiController();
                    $url_request = $this->endpoint . '?kategoripendapatan=' . $kpid . '&nopend=' . $ls->nomor_dirian . '&tahun=' . $this->tahun . '&triwulan=' . $this->triwulan;
                    $request = request(); // Create request instance
                    $request->merge(['end_point' => $url_request]);
                    $response = $apiController->makeRequest($request);
                    Log::info('ProcessSyncPendapatanJob: SUKSES request ke API.', ['url' => $url_request]);
                    $dataPendapatan = $response['data'] ?? [];
                    if (!empty($dataPendapatan)) {
                        foreach ($dataPendapatan as $data) {
                            Log::info($dataPendapatan);
                            $allFetchedData[] = $data;
                            $totalTarget++;
                        }
                    }
                }
            }

            $this->updateApiRequestLog($apiRequestLog, $totalTarget, $allFetchedData);

            foreach ($allFetchedData as $data) {
                $this->processFetchedData($data, $payload);
                $totalSumber++;
                $this->updateApiRequestLogProgress($apiRequestLog, $totalSumber, $totalTarget, $payload);
            }
        } catch (\Exception $e) {
            if ($apiRequestLog) {
                $apiRequestLog->update([
                    'status' => 'gagal',
                ]);
            }

            Log::error('Job ProcessSyncPendapatanJob gagal: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }


    protected function createApiRequestLog()
    {
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        $agent->setUserAgent($this->userAgent);
        $platformRequest = $agent->platform() . '/' . $agent->browser();

        return ApiRequestLog::create([
            'komponen' => 'Pendapatan',
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
        // Initialize size as 0


        // Generate unique identifier for VerifikasiBiayaRutin
        $idVerifikasiPendapatan = trim($data['id_kpc']) . trim($data['tahun_anggaran']) . trim($data['triwulan']);

        // Update or create VerifikasiBiayaRutin
        $pendapatan = Pendapatan::updateOrCreate(
            [
                'id' => $idVerifikasiPendapatan,
            ],
            [
                'id_regional' => $this->id_regional,
                'id_kprk' => $data['id_kprk'],
                'id_kpc' => $data['id_kpc'],
                'tahun' => $data['tahun_anggaran'],
                'triwulan' => $data['triwulan'],
                'tgl_sinkronisasi' => now(),
                'kategori_pendapatan' => $data['kategori_pendapatan'],
                'id_rekening' => $data['koderekening'] ?? null,
                'bulan' => $data['bulan'],
                'rtarif' => $data['rtarif'],
                'tpkirim' => $data['tpkirim'],
                'pelaporan_outgoing' => $data['nominal_outgoing'],
                'pelaporan_incoming' => $data['nominal_incoming'],
                'pelaporan_sisa_layanan' => $data['nominal_sisa_layanan'],
            ]
        );

        $pendapatan->update([
            'id_status' => 7,
            'id_status_kprk' => 7,
        ]);

        $this->updatePayload($data, $payload);
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
            $existing_payload[] = (object) $data;
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
