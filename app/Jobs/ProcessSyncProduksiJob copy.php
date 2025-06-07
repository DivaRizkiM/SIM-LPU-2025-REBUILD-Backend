<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiLog;
use App\Models\JenisBisnis;
use App\Models\Produksi;
use App\Models\ProduksiDetail;
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

class ProcessSyncProduksiJob implements ShouldQueue
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
    protected $tipe_bisnis;

    public function __construct($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $bulan, $tahun, $userAgent, $tipe_bisnis)
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
        $this->tipe_bisnis = $tipe_bisnis;
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
                'komponen' => 'Produksi',
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
                $kategori_bisnis = JenisBisnis::get();
                if ($this->tipe_bisnis) {
                    $kategori_bisnis = JenisBisnis::where('id', $this->tipe_bisnis)->get();
                }

                foreach ($kategori_bisnis as $kb) {
                    $apiController = new ApiController();
                    $url_request = $this->endpoint . '?kd_bisnis=' . $kb->id . '&nopend=' . $ls->id . '&tahun=' . $this->tahun . '&bulan=' . $this->bulan;
                    $request = request(); // Buat instance request
                    $request->merge(['end_point' => $url_request]);
                    $response = $apiController->makeRequest($request);
                    $dataProduksi = $response['data'] ?? [];
                    // dd($dataProduksi);
                    foreach ($dataProduksi as $data) {
                        if (empty($dataProduksi)) {
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
                $id = $data['id_kpc'] . $data['tahun_anggaran'] . $data['bulan'];

                $produksi = Produksi::updateOrCreate(
                    [
                        "id" => $id,
                    ],
                    [
                        'id' => $id,
                        'id_regional' => $data['id_regional'],
                        'id_kprk' => $data['id_kprk'],
                        'id_kpc' => $data['id_kpc'],
                        'tahun_anggaran' => $data['tahun_anggaran'],
                        'bulan' => $data['bulan'],
                        'tgl_singkronisasi' => now(),
                        'status_regional' => 7,
                        'status_kprk' => 7,
                        'bulan' => $data['nama_bulan'],
                    ]
                );

                ProduksiDetail::updateOrCreate(
                    [
                        'id' => $data['id'],

                    ],
                    [
                        'id' => $data['id'],
                        'id_produksi' => $id,
                        'nama_bulan' => $data['nama_bulan'],
                        'kode_bisnis' => $data['kode_bisnis'],
                        'kode_rekening' => $data['koderekening'],
                        'nama_rekening' => $data['nama_rekening'],
                        'rtarif' => $data['rtarif'],
                        'tpkirim' => $data['tpkirim'],
                        'pelaporan' => $data['bsu_pso'],
                        'jenis_produksi' => $data['jenis'],
                        'kategori_produksi' => $data['kategori_produksi'],
                        'keterangan' => $data['keterangan'],
                        'lampiran' => $data['lampiran'],
                    ]
                );
                $categories = [
                    'LAYANAN POS UNIVERSAL' => 'total_lpu',
                    'LAYANAN POS KOMERSIL' => 'total_lpk',
                    'LAYANAN BERBASIS FEE' => 'total_lbf',
                ];

                // Initialize totals array
                $totals = [];

                // Calculate the sum of pelaporan for each category
                foreach ($categories as $kategoriProduksi => $totalField) {
                    $totals[$totalField] = ProduksiDetail::select(DB::raw('SUM(pelaporan) as total'))
                        ->where('id_produksi', $produksi->id)
                        ->where('kategori_produksi', $kategoriProduksi)
                        ->value('total') ?? 0;
                }
                // dd($totals);

                // Update the Produksi model
                // $produksiTotal = Produksi::find($produksi->id);
                $produksi->update(array_merge($totals, [
                    'status_regional' => 7,
                    'status_kprk' => 7,
                ]));
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
            // dd($e);
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
