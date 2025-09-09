<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiLog;
use App\Models\BiayaAtribusi;
use App\Models\BiayaAtribusiDetail;
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

class ProcessSyncAtribusiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $list;
    protected $totalItems;
    protected $endpoint;
    protected $id_regional;
    protected $id_kprk;
    protected $bulan;
    protected $tahun;
    protected $userAgent;

    public function __construct($list, $totalItems, $endpoint, $id_regional, $id_kprk, $bulan, $tahun, $userAgent)
    {
        $this->list = $list;
        $this->totalItems = $totalItems;
        $this->endpoint = $endpoint;
        $this->id_regional = $id_regional;
        $this->id_kprk = $id_kprk;
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
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'Biaya Atribusi',
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
                $apiController = new ApiController();
                $url_request = $this->endpoint . '?bulan=' . $this->bulan . '&id_kprk=' . $ls->id . '&tahun=' . $this->tahun;
                $request = request();
                $request->merge(['end_point' => $url_request]);
                $response = $apiController->makeRequest($request);

                $dataBiayaAtribusi = $response['data'] ?? [];
                foreach ($dataBiayaAtribusi as $data) {
                    if (empty($dataBiayaAtribusi)) {
                        continue;
                    } else {
                        $allFetchedData[] = $data;
                        $totalTarget++;
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

                $biayaAtribusi = BiayaAtribusi::updateOrCreate(
                    [
                        'tahun_anggaran' => $data['tahun_anggaran'],
                        'triwulan' => $data['triwulan'],
                        'id_kprk' => $data['id_kprk'],
                    ],
                    [
                        'id' => $data['id_kprk'] . $data['tahun_anggaran'] . $data['triwulan'],
                        'id_regional' => $this->id_regional,
                        'id_kprk' => $data['id_kprk'],
                        'triwulan' => $data['triwulan'],
                        'tahun_anggaran' => $data['tahun_anggaran'],
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
                        'id_biaya_atribusi' => $data['id_kprk'] . $data['tahun_anggaran'] . $data['triwulan'],
                        'id_rekening_biaya' => $data['koderekening'],
                        'bulan' => $data['bulan'],
                        'pelaporan' => $data['nominal'],
                        'keterangan' => $data['keterangan'],
                        'lampiran' => $data['lampiran'],
                    ]
                );

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

                if ($data['lampiran']) {

                    $namaFile = $data['lampiran'] ?? null;
                    // dd($namaFile);
                    $destinationPath = storage_path('/app/public/lampiran');

                    $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --delete rsync://kominfo2@103.123.39.227:/lpu/{$namaFile} {$destinationPath} 2>&1";
                    $output = shell_exec($rsyncCommand);
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
