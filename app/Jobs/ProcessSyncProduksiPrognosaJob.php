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
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Support\Facades\Log;

class ProcessSyncProduksiPrognosaJob implements ShouldQueue
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
    protected $tipe_bisnis;

    public function __construct($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $triwulan, $tahun, $userAgent, $tipe_bisnis)
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
        $this->tipe_bisnis = $tipe_bisnis;
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

            foreach ($this->list as $ls) {
                $kategori_bisnis = JenisBisnis::get();
                if ($this->tipe_bisnis) {
                    $kategori_bisnis = JenisBisnis::where('id', $this->tipe_bisnis)->get();
                }

                foreach ($kategori_bisnis as $kb) {
                    $kd_bisnis = str_pad($kb->id, 2, '0', STR_PAD_LEFT);

                    $apiController = new ApiController();
                    $url_request = $this->endpoint . '?kd_bisnis=' . $kd_bisnis . '&nopend=' . $ls->nomor_dirian . '&tahun=' . $this->tahun . '&triwulan=' . $this->triwulan;
                    $request = request();
                    $request->merge(['end_point' => $url_request]);
                    $response = $apiController->makeRequest($request);
                    $dataProduksi = $response['data'] ?? [];

                    foreach ($dataProduksi as $data) {
                        if (!empty($dataProduksi)) {
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
            // ⛔ Gagal -> Update status menjadi "gagal"
            if ($apiRequestLog) {
                $apiRequestLog->update([
                    'status' => 'gagal',
                ]);
            }

            // ✅ Log error
            Log::error('Job ProcessSyncProduksiPrognosaJob gagal: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // Tetap throw agar job ditandai failed di Laravel
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
            'komponen' => 'Produksi Prognosa',
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
        $id = trim($data['id_kpc']) . trim($data['tahun_anggaran']) . trim($data['triwulan']);

        $produksi = Produksi::updateOrCreate(
            [
                "id" => $id,
            ],
            [
                'id_regional' => $data['id_regional'],
                'id_kprk' => $data['id_kprk'],
                'id_kpc' => $data['id_kpc'],
                'tahun_anggaran' => $data['tahun_anggaran'],
                'triwulan' => $data['triwulan'],
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
                'id_produksi' => $id,
                'nama_bulan' => $data['nama_bulan'],
                'kode_bisnis' => $data['kode_bisnis'],
                'kode_rekening' => $data['koderekening'],
                'nama_rekening' => $data['nama_rekening'],
                'rtarif' => $data['rtarif'],
                'tpkirim' => $data['tpkirim'],
                'pelaporan_prognosa' => $data['bsu_pso'],
                'bsu_bruto_prognosa' => $data['bsu_bruto'] ?? null,
                'bilangan_prognosa' => $data['bilangan'] ?? null,
                'jenis_produksi' => $data['jenis'],
                'kategori_produksi' => $data['kategori_produksi'],
                'keterangan' => $data['keterangan'],
                'lampiran' => $data['lampiran'],
            ]
        );

        $this->updateProduksiTotals($produksi);

        if (!empty($data['lampiran'])) {
            $this->syncLampiran($data['lampiran']);
        }

        $this->updatePayload($data, $payload);
    }

    protected function updateProduksiTotals($produksi)
    {
        $categories = [
            'LAYANAN POS UNIVERSAL' => 'total_lpu_prognosa',
            'LAYANAN POS KOMERSIL' => 'total_lpk_prognosa',
            'LAYANAN BERBASIS FEE' => 'total_lbf_prognosa',
        ];

        $totals = [];

        foreach ($categories as $kategoriProduksi => $totalField) {
            $totals[$totalField] = ProduksiDetail::select(DB::raw('SUM(pelaporan_prognosa) as total'))
                ->where('id_produksi', $produksi->id)
                ->where('kategori_produksi', $kategoriProduksi)
                ->value('total') ?? 0;
        }

        $produksi->update(array_merge($totals, [
            'status_regional' => 7,
            'status_kprk' => 7,
        ]));
    }

    protected function syncLampiran($namaFile)
    {
        $destinationPath = storage_path('/app/public/lampiran');
        $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --delete rsync://kominfo2@103.123.39.227:/lpu/{$namaFile} {$destinationPath} 2>&1";
        shell_exec($rsyncCommand);
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
