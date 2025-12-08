<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\KategoriBiaya;
use App\Models\VerifikasiBiayaRutin;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\VerifikasiBiayaRutinDetailLampiran;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class ProcessSyncBiayaPrognosaJob implements ShouldQueue
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

            // ✅ Loop KPC
            foreach ($this->list as $ls) {
                $kategori_biaya = KategoriBiaya::get();
                
                // ✅ Loop Kategori Biaya
                foreach ($kategori_biaya as $kb) {
                    $kbid = str_pad($kb->id, 2, '0', STR_PAD_LEFT);
                    
                    $apiController = new ApiController();
                    $url_request = $this->endpoint . '?kategoribiaya=' . $kbid . '&nopend=' . $ls->nomor_dirian . '&tahun=' . $this->tahun . '&triwulan=' . $this->triwulan;
                    
                    $request = request();
                    $request->merge(['end_point' => $url_request]);
                    $response = $apiController->makeRequest($request);
                    
                    Log::info('ProcessSyncBiayaPrognosaJob: SUKSES request ke API.', ['url' => $url_request]);
                    
                    $dataBiayaRutin = $response['data'] ?? [];
                    
                    if (!empty($dataBiayaRutin)) {
                        foreach ($dataBiayaRutin as $data) {
                            Log::info($dataBiayaRutin);
                            $allFetchedData[] = $data;
                            $totalTarget++;
                        }
                    }
                }
            }

            $this->updateApiRequestLog($apiRequestLog, $totalTarget, $allFetchedData);

            // ✅ Process each data dan update payload secara incremental
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

            Log::error('Job ProcessSyncBiayaPrognosaJob gagal: ' . $e->getMessage(), [
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
            'komponen' => 'Biaya Rutin Prognosa',
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
        // Generate unique identifier for VerifikasiBiayaRutin
        $idVerifikasiBiayaRutin = trim($data['id_kpc']) . trim($data['tahun_anggaran']) . trim($data['triwulan']);

        // Update or create VerifikasiBiayaRutin
        $biayaRutin = VerifikasiBiayaRutin::updateOrCreate(
            [
                'id' => $idVerifikasiBiayaRutin,
            ],
            [
                'tahun' => $data['tahun_anggaran'],
                'triwulan' => $data['triwulan'],
                'id_kpc' => $data['id_kpc'],
                'id_regional' => $this->id_regional,
                'id_kprk' => $data['id_kprk'],
                'total_biaya_prognosa' => $data['nominal'],
                'tgl_singkronisasi' => now(),
                'id_status' => 7,
                'id_status_kprk' => 7,
                'id_status_kpc' => 7,
            ]
        );

        // Update or create VerifikasiBiayaRutinDetail
        VerifikasiBiayaRutinDetail::updateOrCreate(
            [
                'id' => $data['id'],
            ],
            [
                'id_verifikasi_biaya_rutin' => $idVerifikasiBiayaRutin,
                'id_rekening_biaya' => $data['koderekening'],
                'bulan' => $data['bulan'],
                'pelaporan_prognosa' => $data['nominal'],
                'bilangan_prognosa' => $data['bilangan'] ?? null,
                'kategori_biaya' => $data['kategori_biaya'],
                'keterangan_prognosa' => $data['keterangan'],
                'lampiran' => $data['lampiran'],
            ]
        );

        // Hitung total pelaporan prognosa
        $totalPelaporanPrognosa = VerifikasiBiayaRutinDetail::where('id_verifikasi_biaya_rutin', $idVerifikasiBiayaRutin)
            ->sum('pelaporan_prognosa');

        $biayaRutinTotal = VerifikasiBiayaRutin::where('id', $idVerifikasiBiayaRutin)->first();
        $biayaRutinTotal->update([
            'total_biaya_prognosa' => (float) ($totalPelaporanPrognosa ?? 0),
            'id_status' => 7,
            'id_status_kprk' => 7,
        ]);

        // Handle lampiran jika ada
        if ($data['lampiran'] === 'Y') {
            $apiControllerLapiran = new ApiController();
            $url_request_lampiran = 'lampiran_biaya?id_biaya=' . $data['id'];
            $request = request();
            $request->merge(['end_point' => $url_request_lampiran]);
            $response = $apiControllerLapiran->makeRequest($request);

            $lampiranList = $response['data'] ?? [];

            // Normalisasi: kalau yang datang single object, jadikan array satu elemen
            if (!is_array($lampiranList) || (is_array($lampiranList) && array_is_list($lampiranList) === false && isset($lampiranList['id']))) {
                $lampiranList = [$lampiranList];
            }

            foreach ($lampiranList as $lp) {
                VerifikasiBiayaRutinDetailLampiran::updateOrCreate(
                    ['id' => $lp['id']], 
                    [
                        'verifikasi_biaya_rutin_detail' => $data['id'],
                        'nama_file' => $lp['nama_file'],
                    ]
                );
            }
        }

        // ✅ Update payload secara incremental (sama seperti ProcessSyncBiayaJob)
        $this->updatePayload($data, $payload);
    }

    protected function updatePayload($data, $payload)
    {
        $updated_payload = $payload->payload ?? '';
        $jsonData = json_encode($data);
        $fileSize = strlen($jsonData);
        $data['size'] = $fileSize;

        if ($updated_payload !== '' || $payload->payload !== null) {
            // ✅ Decode existing payload dari JSON string ke PHP array
            $existing_payload = json_decode($updated_payload, true);
            $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];
            
            // ✅ Tambahkan data baru ke array
            $existing_payload[] = (object) $data;
            
            // ✅ Encode kembali ke JSON
            $updated_payload = json_encode($existing_payload);
        } else {
            $updated_payload = json_encode([(object) $data]);
        }

        $payload->update(['payload' => $updated_payload]);
    }

    protected function updateApiRequestLogProgress($apiRequestLog, $totalSumber, $totalTarget, $payload)
    {
        // ✅ Fix: Status berubah jadi "success" saat 100%
        $status = ($totalSumber == $totalTarget) ? 'success' : 'on progress';
        
        $apiRequestLog->update([
            'successful_records' => $totalSumber,
            'status' => $status,
        ]);
    }
}
