<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController; // pakai ApiController/ApiControllerV2 sesuai yang kamu gunakan
use App\Models\JenisBisnis;
use App\Models\Produksi;
use App\Models\ProduksiDetail;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class ProcessSyncProduksiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = [10, 60, 300];

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

    public function __construct($list, $totalItems, $endpoint, $id_regional, $id_kprk, $id_kpc, $bulan, $triwulan, $tahun, $userAgent, $tipe_bisnis)
    {
        $this->list        = $list;
        $this->totalItems  = $totalItems;
        $this->endpoint    = $endpoint;
        $this->id_regional = $id_regional;
        $this->id_kprk     = $id_kprk;
        $this->id_kpc      = $id_kpc;
        $this->bulan       = $bulan;
        $this->triwulan    = $triwulan;
        $this->tahun       = $tahun;
        $this->userAgent   = $userAgent;
        $this->tipe_bisnis = $tipe_bisnis;
    }

    public function handle()
    {
        $apiRequestLog = null;

        try {
            $serverIpAddress  = gethostbyname(gethostname());
            $agent            = new Agent();
            $agent->setUserAgent($this->userAgent);
            $platform_request = $agent->platform() . '/' . $agent->browser();

            $totalTarget    = 0;
            $totalSumber    = 0;
            $allFetchedData = [];

            $apiRequestLog = ApiRequestLog::create([
                'komponen'          => 'Produksi',
                'tanggal'           => now(),
                'ip_address'        => $serverIpAddress,
                'platform_request'  => $platform_request,
                'successful_records' => 0,
                'available_records' => 0,
                'total_records'     => 0,
                'status'            => 'Memuat Data',
            ]);

            $payload = ApiRequestPayloadLog::create([
                'api_request_log_id' => $apiRequestLog->id,
                'payload'            => null,
            ]);

            // daftar bisnis (filter jika tipe_bisnis diisi)
            $kategori_bisnis = $this->tipe_bisnis
                ? JenisBisnis::where('id', $this->tipe_bisnis)->get()
                : JenisBisnis::get();

            $apiController = new ApiController(); // atau ApiControllerV2 jika itu yang dipakai

            foreach ($this->list as $ls) {
                foreach ($kategori_bisnis as $kb) {
                    // kd_bisnis di-zeropad 2 digit: 1 -> 01, 2 -> 02, ..., 10 -> 10
                    $kdBisnisInt = (int) ($kb->kd_bisnis ?? $kb->id); // prefer kolom kd_bisnis kalau ada
                    $kd_bisnis   = sprintf('%02d', $kdBisnisInt);

                    // API: /pso/1.0.0/data/produksi_bulanan?bulan=12&kd_bisnis=10&nopend=20356&tahun=2022
                    $url_request = $this->endpoint
                        . '?bulan=' . $this->bulan
                        . '&kd_bisnis=' . $kd_bisnis
                        . '&nopend=' . ($ls->nomor_dirian ?? $ls->id) // nopend = nomor_dirian
                        . '&tahun=' . $this->tahun;

                    // buat Request terisolasi (jangan pakai helper request() di Job)
                    $req = new HttpRequest();
                    $req->merge(['end_point' => $url_request]);

                    $jsonResponse = $apiController->makeRequest($req);

                    // ubah ke array:
                    $payloadArr = $jsonResponse instanceof \Illuminate\Http\JsonResponse
                        ? $jsonResponse->getData(true)
                        : (is_array($jsonResponse) ? $jsonResponse : []);

                    // logging panggilan API
                    $httpStatus  = method_exists($jsonResponse, 'getStatusCode') ? $jsonResponse->getStatusCode() : null;
                    $bodyPreview = substr(json_encode($payloadArr), 0, 500);
                    Log::info('Call API produksi_bulanan', [
                        'url'        => $url_request,
                        'status'     => $httpStatus,
                        'body_first' => $bodyPreview,
                    ]);

                    // fleksibel ambil koleksi data
                    $dataProduksi = [];
                    if (isset($payloadArr['data']) && is_array($payloadArr['data'])) {
                        $dataProduksi = $payloadArr['data'];
                    } elseif (isset($payloadArr['result']) && is_array($payloadArr['result'])) {
                        $dataProduksi = $payloadArr['result'];
                    } elseif (isset($payloadArr['payload']) && is_array($payloadArr['payload'])) {
                        $dataProduksi = $payloadArr['payload'];
                    }

                    Log::info('Produksi fetched', [
                        'nopend'    => $ls->nomor_dirian ?? null,
                        'kd_bisnis' => $kd_bisnis,
                        'count'     => is_countable($dataProduksi) ? count($dataProduksi) : 0,
                    ]);

                    if (!empty($dataProduksi)) {
                        foreach ($dataProduksi as $data) {
                            if (!is_array($data)) continue;
                            $allFetchedData[] = $data;
                            $totalTarget++;
                        }
                    }
                }
            }

            $status = empty($allFetchedData) ? 'data tidak tersedia' : 'on progress';

            $apiRequestLog->update([
                'total_records'     => $totalTarget,
                'available_records' => $totalTarget, // fallback aman
                'status'            => $status,
            ]);

            // pastikan folder lampiran ada
            @mkdir(storage_path('app/public/lampiran'), 0775, true);

            foreach ($allFetchedData as $data) {
                // ID unik produksi (sesuaikan kebutuhanmu)
                $id = trim((string)($data['id_kpc'] ?? ''))
                    . trim((string)($data['tahun_anggaran'] ?? ''))
                    . trim((string)($data['nama_bulan'] ?? ''));

                $produksi = Produksi::updateOrCreate(
                    ['id' => $id],
                    [
                        'id'               => $id,
                        'id_regional'      => $data['id_regional']   ?? null,
                        'id_kprk'          => $data['id_kprk']       ?? null,
                        'id_kpc'           => $data['id_kpc']        ?? null,
                        'tahun_anggaran'   => $data['tahun_anggaran'] ?? null,
                        'bulan'            => $data['nama_bulan']    ?? null,
                        'tgl_singkronisasi' => now(),
                        'status_regional'  => 7,
                        'status_kprk'      => 7,
                    ]
                );

                ProduksiDetail::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'id_produksi'       => $id,
                        'nama_bulan'        => $data['nama_bulan']        ?? null,
                        'kode_bisnis'       => $data['kode_bisnis']       ?? null,
                        'kode_rekening'     => $data['koderekening']      ?? null,
                        'nama_rekening'     => $data['nama_rekening']     ?? null,
                        'rtarif'            => $data['rtarif']            ?? null,
                        'tpkirim'           => $data['tpkirim']           ?? null,
                        'pelaporan'         => $data['bsu_pso']           ?? 0,
                        'jenis_produksi'    => $data['jenis']             ?? null,
                        'kategori_produksi' => $data['kategori_produksi'] ?? null,
                        'keterangan'        => $data['keterangan']        ?? null,
                        'lampiran'          => $data['lampiran']          ?? null,
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
                    $totals[$totalField] = (float) (ProduksiDetail::select(DB::raw('SUM(pelaporan) as total'))
                        ->where('id_produksi', $produksi->id)
                        ->where('kategori_produksi', $kategoriProduksi)
                        ->value('total') ?? 0);
                }

                $produksi->update(array_merge($totals, [
                    'status_regional' => 7,
                    'status_kprk'     => 7,
                ]));

                // sinkron lampiran (jika ada) → rsync server baru
                if (!empty($data['lampiran'])) {
                    $namaFile        = $data['lampiran'];
                    $destinationPath = storage_path('app/public/lampiran');
                    // gunakan port 2129 + IP baru
                    $rsyncCommand    = "export RSYNC_PASSWORD='k0minf0!'; rsync -arvz --port=2129 --delete rsync://kominfo2@103.123.39.226/lpu/{$namaFile} {$destinationPath} 2>&1";
                    $output          = shell_exec($rsyncCommand);
                    Log::info('rsync lampiran', ['file' => $namaFile, 'out' => $output]);
                }

                $totalSumber++;

                // simpan payload ringkasan (append)
                $updated_payload = $payload->payload ?? '';
                $dataWithSize    = $data;
                $dataWithSize['size'] = strlen(json_encode($data));

                if ($updated_payload !== '' || $payload->payload !== null) {
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];
                    $existing_payload[] = (object) $dataWithSize;
                    $updated_payload = json_encode($existing_payload);
                } else {
                    $updated_payload = json_encode([(object) $dataWithSize]);
                }

                $payload->update(['payload' => $updated_payload]);

                $apiRequestLog->update([
                    'successful_records' => $totalSumber,
                    'status'             => ($totalSumber === $totalTarget) ? 'success' : 'on progress',
                ]);
            }

            Log::info('ProcessSyncProduksiJob selesai', [
                'total_target' => $totalTarget,
                'total_ok'     => $totalSumber,
                'status'       => ($totalSumber === $totalTarget) ? 'success' : 'on progress',
            ]);
        } catch (\Exception $e) {
            if ($apiRequestLog) {
                $apiRequestLog->update(['status' => 'gagal']);
            }
            Log::error('Job ProcessSyncProduksiJob gagal: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // biar masuk failed_jobs
        }
    }
}
