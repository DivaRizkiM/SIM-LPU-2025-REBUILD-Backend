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
    protected $triwulan;
    protected $tahun;
    protected $userAgent;
    protected $tipe_bisnis;

    public function __construct($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $bulan, $tahun, $userAgent, $tipe_bisnis)
    {
        $this->list = $list;
        $this->totalItems = $totalItems;
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
        $apiRequestLog = null;

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
                'payload' => null,
            ]);

            foreach ($this->list as $ls) {
                $kategori_bisnis = JenisBisnis::get();
                if ($this->tipe_bisnis) {
                    $kategori_bisnis = JenisBisnis::where('id', $this->tipe_bisnis)->get();
                }

                foreach ($kategori_bisnis as $kb) {
                    try {
                        $apiController = new ApiController();

                        // Zero-pad kd_bisnis (1 -> 01, 2 -> 02, ..., 10 -> 10)
                        $kdBisnisInt = (int) ($kb->kd_bisnis ?? $kb->id);
                        $kd_bisnis   = sprintf('%02d', $kdBisnisInt);

                        $url_request = $this->endpoint
                            . '?bulan=' . $this->bulan
                            . '&kd_bisnis=' . $kd_bisnis
                            . '&nopend=' . $ls->id   // kalau perlu nomor_dirian: ganti $ls->id -> $ls->nomor_dirian
                            . '&tahun=' . $this->tahun;

                        $request = request();
                        $request->merge(['end_point' => $url_request]);

                        $response = $apiController->makeRequest($request);

                        // JsonResponse -> array aman
                        $payloadArr = $response instanceof \Illuminate\Http\JsonResponse
                            ? $response->getData(true)
                            : (is_array($response) ? $response : []);

                        // Ambil koleksi data (coba beberapa key umum)
                        $dataProduksi = [];
                        if (isset($payloadArr['data']) && is_array($payloadArr['data'])) {
                            $dataProduksi = $payloadArr['data'];
                        } elseif (isset($payloadArr['result']) && is_array($payloadArr['result'])) {
                            $dataProduksi = $payloadArr['result'];
                        } elseif (isset($payloadArr['payload']) && is_array($payloadArr['payload'])) {
                            $dataProduksi = $payloadArr['payload'];
                        }

                        if (empty($dataProduksi)) {
                            Log::info('Produksi kosong/diabaikan', [
                                'nopend'    => $ls->id,
                                'kd_bisnis' => $kd_bisnis,
                                'url'       => $url_request,
                            ]);
                            continue;
                        }

                        foreach ($dataProduksi as $data) {
                            if (!is_array($data)) continue;
                            $allFetchedData[] = $data;
                            $totalTarget++;
                        }
                    } catch (\Throwable $ex) {
                        // Error per item → jangan fail job, cukup log dan lanjut
                        Log::warning('Gagal fetch 1 item (diabaikan)', [
                            'nopend'    => $ls->id,
                            'kd_bisnis' => $kb->kd_bisnis ?? $kb->id,
                            'msg'       => $ex->getMessage(),
                        ]);
                        continue;
                    }
                }
            }

            $status = empty($allFetchedData) ? 'data tidak tersedia' : 'on progress';

            $apiRequestLog->update([
                'total_records'     => $totalTarget,
                'available_records' => $totalTarget,
                'status'            => $status,
            ]);

            // Pastikan folder lampiran ada
            @mkdir(storage_path('/app/public/lampiran'), 0775, true);

            foreach ($allFetchedData as $data) {
                // --- siapkan triwulan: dari response (trim), fallback dari bulan ---
                $tw = null;
                if (isset($data['triwulan']) && trim((string)$data['triwulan']) !== '') {
                    $tw = (int) trim((string) $data['triwulan']);   // contoh "1 " -> 1
                } elseif (isset($data['nama_bulan']) && is_numeric($data['nama_bulan'])) {
                    $tw = (int) ceil(((int) $data['nama_bulan']) / 3);
                }

                // ID unik produksi
                $id = ($data['id_kpc'] ?? '') . ($data['tahun_anggaran'] ?? '') . ($data['nama_bulan'] ?? '');

                $produksi = Produksi::updateOrCreate(
                    ['id' => $id],
                    [
                        'id'                => $id,
                        'id_regional'       => $data['id_regional'] ?? null,
                        'id_kprk'           => $data['id_kprk'] ?? null,
                        'id_kpc'            => $data['id_kpc'] ?? null,
                        'tahun_anggaran'    => $data['tahun_anggaran'] ?? null,
                        'bulan'             => $data['nama_bulan'] ?? null,
                        'triwulan'          => $tw, // <-- simpan triwulan
                        'tgl_singkronisasi' => now(),
                        'status_regional'   => 7,
                        'status_kprk'       => 7,
                    ]
                );

                // (opsional) logging bantu debug triwulan
                Log::info('Triwulan disimpan', [
                    'raw_triwulan' => $data['triwulan'] ?? null,
                    'nama_bulan'   => $data['nama_bulan'] ?? null,
                    'saved_tw'     => $tw,
                    'id_produksi'  => $id,
                ]);

                ProduksiDetail::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'id'             => $data['id'],
                        'id_produksi'    => $id,
                        'nama_bulan'     => $data['nama_bulan'] ?? null,
                        'kode_bisnis'    => $data['kode_bisnis'] ?? null,
                        'kode_rekening'  => $data['koderekening'] ?? null,
                        'nama_rekening'  => $data['nama_rekening'] ?? null,
                        'rtarif'         => $data['rtarif'] ?? null,
                        'tpkirim'        => $data['tpkirim'] ?? null,
                        'pelaporan'      => $data['bsu_pso'] ?? 0,
                        'bsu_bruto'      => $data['bsu_bruto'] ?? null,
                        'bilangan'      => $data['bilangan'] ?? null,
                        'jenis_produksi' => $data['jenis'] ?? null,
                        'kategori_produksi' => $data['kategori_produksi'] ?? null,
                        'keterangan'     => $data['keterangan'] ?? null,
                        'lampiran'       => $data['lampiran'] ?? null,
                    ]
                );

                // rekap kategori → total_lpu/lpk/lbf
                $categories = [
                    'LAYANAN POS UNIVERSAL' => 'total_lpu',
                    'LAYANAN POS KOMERSIL'  => 'total_lpk',
                    'LAYANAN BERBASIS FEE'  => 'total_lbf',
                ];

                $totals = [];
                foreach ($categories as $kategoriProduksi => $totalField) {
                    $totals[$totalField] = ProduksiDetail::select(DB::raw('SUM(pelaporan) as total'))
                        ->where('id_produksi', $produksi->id)
                        ->where('kategori_produksi', $kategoriProduksi)
                        ->value('total') ?? 0;
                }

                $produksi->update(array_merge($totals, [
                    'status_regional' => 7,
                    'status_kprk'     => 7,
                ]));

                // rsync lampiran (jika ada)
                if (!empty($data['lampiran'])) {
                    $namaFile = $data['lampiran'];
                    $destinationPath = storage_path('/app/public/lampiran');
                    $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --port=2129 --delete rsync://kominfo2@103.123.39.226/lpu/{$namaFile} {$destinationPath} 2>&1";
                    $output = shell_exec($rsyncCommand);
                    Log::info('rsync lampiran', ['file' => $namaFile, 'out' => $output]);
                }

                $totalSumber++;

                $status = ($totalSumber == $totalTarget) ? 'success' : 'on progress';

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

                $apiRequestLog->update([
                    'successful_records' => $totalSumber,
                    'status' => $status,
                ]);
            }
        } catch (\Exception $e) {
            // Tidak ada beginTransaction di atas, jadi jangan rollBack

            if ($apiRequestLog) {
                $apiRequestLog->update(['status' => 'gagal']);
            }

            Log::error('Job ProcessSyncProduksiJob gagal: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // Supaya job tidak langsung fail untuk kasus non-fatal, jangan lempar ulang.
            // Jika ingin fail untuk error fatal, aktifkan baris berikut:
            // throw $e;
        }
    }

    protected function createApiRequestLog()
    {
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        $agent->setUserAgent($this->userAgent);
        $platformRequest = $agent->platform() . '/' . $agent->browser();

        return ApiRequestLog::create([
            'komponen' => 'Produksi',
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
            ["id" => $id],
            [
                'triwulan' => (isset($data['triwulan']) && trim((string)$data['triwulan']) !== '')
                    ? (int) trim((string)$data['triwulan'])
                    : (isset($data['nama_bulan']) ? (int) ceil(((int)$data['nama_bulan']) / 3) : null),
                'id_regional' => $data['id_regional'] ?? null,
                'id_kprk' => $data['id_kprk'] ?? null,
                'id_kpc' => $data['id_kpc'] ?? null,
                'tahun_anggaran' => $data['tahun_anggaran'] ?? null,
                'tgl_singkronisasi' => now(),
                'status_regional' => 7,
                'status_kprk' => 7,
            ]
        );

        ProduksiDetail::updateOrCreate(
            ['id' => $data['id']],
            [
                'nama_bulan' => $data['nama_bulan'] ?? null,
                'id_produksi' => $id,
                'kode_bisnis' => $data['kode_bisnis'] ?? null,
                'kode_rekening' => $data['koderekening'] ?? null,
                'nama_rekening' => $data['nama_rekening'] ?? null,
                'jenis_produksi' => $data['jenis'] ?? null,
                'kategori_produksi' => $data['kategori_produksi'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'rtarif' => $data['rtarif'] ?? null,
                'tpkirim' => $data['tpkirim'] ?? null,
                'pelaporan' => $data['bsu_pso'] ?? 0,
                'bsu_bruto' => $data['bsu_bruto'] ?? null,
                'bilangan' => $data['bilangan'] ?? null,
                'bilangan' => $data['bilangan'] ?? null,
                'lampiran' => $data['lampiran'] ?? null,
            ]
        );

        $this->updateProduksiTotals($produksi);
        $this->updatePayload($data, $payload);
    }

    protected function updateProduksiTotals($produksi)
    {
        $categories = [
            'LAYANAN POS UNIVERSAL' => 'total_lpu',
            'LAYANAN POS KOMERSIL' => 'total_lpk',
            'LAYANAN BERBASIS FEE' => 'total_lbf',
        ];

        $totals = [];

        foreach ($categories as $kategoriProduksi => $totalField) {
            $totals[$totalField] = ProduksiDetail::select(DB::raw('SUM(pelaporan) as total'))
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
        $rsyncCommand = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --port=2129 --delete rsync://kominfo2@103.123.39.226/lpu/{$namaFile} {$destinationPath} 2>&1";
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
