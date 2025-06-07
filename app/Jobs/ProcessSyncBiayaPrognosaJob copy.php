<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiLog;
use App\Models\KategoriBiaya;
use App\Models\VerifikasiBiayaRutin;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\VerifikasiBiayaRutinDetailLampiran;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;

class ProcessSyncBiayaPrognosaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $list;
    protected $totalItems;
    protected $endpoint;
    protected $id_regional;
    protected $id_kprk;
    protected $id_kpc;
    protected $bulan;
    protected $tahun;
    protected $userAgent;

    public function __construct($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $bulan, $tahun, $userAgent)
    {
        $this->list = $list;
        $this->totalItems = $totalItems; // Simpan jumlah total item
        $this->endpoint = $endpoint;
        $this->id_regional = $id_regional;
        $this->id_kprk = $id_kprk;
        $this->id_kpc = $id_kpc;
        $this->bulan = $bulan;
        $this->tahun = $tahun;
        $this->userAgent = $userAgent;
    }

    public function handle()
    {
        try {
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $agent->setUserAgent($this->userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;

            $totalTarget = 0;
            $totalSumber = 0;
            $allFetchedData = [];
            $ssid = null;
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Biaya Rutin Prognosa',
                'tanggal' => now(),
                'ip_address' => $serverIpAddress,
                'platform_request' => $platform_request,
                'successful_records' => 0,
                'available_records' => 0,
                'total_records' => 0,
                'status' => 'Memuat Data',
            ]);
           $payload = ApiRequestPayloadLog::create([
                'api_request_log_id' => $apiRequestLog->id,
                'payload' => null, // Store the payload as JSON
            ]);

            foreach ($this->list as $ls) {
                $kategori_biaya = KategoriBiaya::get();
                foreach ($kategori_biaya as $kb) {
                    $apiController = new ApiController();
                    $url_request = $this->endpoint . '?bulan=' . $this->bulan . '&kategoribiaya=' . $kb->id . '&nopend=' . $ls->id . '&tahun=' . $this->tahun;
                    $request = request(); // Buat instance request
                    $request->merge(['end_point' => $url_request]);
                    $response = $apiController->makeRequest($request);
                    // $ssid = $response['access_token'];
                    $dataBiayaRutin = $response['data'] ?? [];
                    foreach ($dataBiayaRutin as $data) {
                        if (empty($dataBiayaRutin)) {
                            continue;
                        } else {
                            $allFetchedData[] = $data;
                            $totalTarget++;
                        }
                    }
                }
            }
            $status = 'on progress';
            if ($allFetchedData == []) {
                $status = 'data tidak tersedia';
            }
            $apiRequestLog->update([
                'total_records'=> $totalTarget,
                'available_records' => $response['total_data'] ??$totalTarget,
                'status' => $status,
            ]);
            foreach ($allFetchedData as $data) {

                $biayaRutin = VerifikasiBiayaRutin::updateOrCreate(
                    [
                        'tahun' => $data['tahun_anggaran'],
                        'triwulan' => $data['triwulan'],
                        'id_kpc' => $data['id_kpc'],
                        'id' => $data['id_kpc'] . $data['tahun_anggaran'] . $data['triwulan'],
                    ],
                    [
                        'id_regional' => $ls->id_regional,
                        'id_kprk' => $data['id_kprk'],
                        'total_biaya_prognosa' => $data['nominal'],
                        'tgl_singkronisasi' => now(),
                        'id_status' => 7,
                        'id_status_kprk' => 7,
                        'id_status_kpc' => 7,
                        'bulan' => $data['bulan'],
                    ]
                );

                VerifikasiBiayaRutinDetail::updateOrCreate(
                    [
                        'id_verifikasi_biaya_rutin' => $data['id_kpc'] . $data['tahun_anggaran'] . $data['triwulan'],
                        'id_rekening_biaya' => $data['koderekening'],
                        'bulan' => $data['bulan'],
                        'id' => $data['id'],

                    ],
                    [
                        'pelaporan' => $data['nominal'],
                        'kategori_biaya' => $kb->nama,
                        'keterangan' => $data['keterangan'],
                        'lampiran' => $data['lampiran'],
                    ]
                );
                $biayaRutinDetail = VerifikasiBiayaRutinDetail::select(DB::raw('SUM(pelaporan) as total_pelaporan'))
                    ->where('id_verifikasi_biaya_rutin', $data['id_kpc'] . $data['tahun_anggaran'] . $data['triwulan'])
                    ->first();
                $biayaRutinTotal = VerifikasiBiayaRutin::where('id', $data['id_kpc'] . $data['tahun_anggaran'] . $data['triwulan'])
                    ->first();
                $biayaRutinTotal->update([
                    'total_biaya_prognosa' => $biayaRutinDetail->total_pelaporan,
                    'id_status' => 7,
                    'id_status_kprk' => 7,
                ]);
                if ($data['lampiran'] == 'Y') {
                    $apiControllerLapiran = new ApiController();
                    $url_request_lampiran = 'lampiran_biaya?id_biaya=' . $data['id'];
                    $request = request(); // Buat instance request
                    $request->merge(['end_point' => $url_request_lampiran]);
                    $response = $apiControllerLapiran->makeRequest($request);
                    $lampiran = $response['data'] ?? [];
                    if ($lampiran !== []) {
                        $detail_lampiran = VerifikasiBiayaRutinDetailLampiran::where('verifikasi_biaya_rutin_detail', $data['id'])->first();
                        if ($detail_lampiran) {
                            $detail_lampiran->update([
                                'nama_file' => $lampiran['nama_file'],
                            ]);
                        } else {
                            VerifikasiBiayaRutinDetailLampiran::create([
                                'verifikasi_biaya_rutin_detail' => $lampiran['id_biaya'],
                                'nama_file' => $lampiran['nama_file'],
                                'id' => $lampiran['id'],
                            ]);
                        }
                        $namaFile = $data['nama_file'] ?? null;
                        $destinationPath = storage_path('/app/public/lampiran');
                        $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --delete rsync://kominfo2@103.123.39.227:/lpu/{$namaFile} {$destinationPath} 2>&1";
                        $output = shell_exec($rsyncCommand);
                    }
                }
                $totalSumber++;


                $status = ($totalSumber == $totalTarget) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
                    $updated_payload = json_encode($existing_payload);
                } else {
                    $updated_payload = json_encode([(object) $data]);
                }

                sleep(2);
                $payload->update([
                    'payload' => $updated_payload,
                ]);

                $apiRequestLog->update([
                    'successful_records' => $totalSumber,
                    'status' => $status,
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
        }
    }
    public function maxAttempts()
    {
        return 5; // Set the maximum attempts
    }

    public function backoff()
    {
        return 10; // Set the delay in seconds before retrying the job
    }
    public function timeout()
{
    return 0; // No timeout for this job
}

}
