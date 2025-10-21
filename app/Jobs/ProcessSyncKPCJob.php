<?php

namespace App\Jobs;

use App\Models\Kpc;
use App\Models\Kprk;
use App\Models\ApiLog;
use Jenssegers\Agent\Agent;
use App\Models\ApiRequestLog;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\ApiController;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessSyncKPCJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $endpoint;
    protected $userAgent;
    protected $page;
    protected $perPage;

    public function __construct($endpoint, $userAgent, $page = 1, $perPage = 1000)
    {
        $this->endpoint = $endpoint;
        $this->userAgent = $userAgent;
        $this->page = $page;
        $this->perPage = $perPage;
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

            $apiController = new ApiController();
            $req = Request::create('/', 'GET', [
                'end_point' => $this->endpoint,
                'page' => $this->page,
                'per_page' => $this->perPage,
            ]);

            $response = $apiController->makeRequest($req);
            $data = $response['data'] ?? [];

            if (empty($data)) {
                return;
            }

            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'KPC',
                'tanggal' => now(),
                'ip_address' => $serverIpAddress,
                'platform_request' => $platform_request,
                'successful_records' => 0,
                'total_records' => $response['total_data'] ?? count($data),
                'available_records' => count($data),
                'status' => 'Memproses Batch',
            ]);

            $samplePayload = isset($data[0]) ? json_encode($data[0]) : null;
            ApiRequestPayloadLog::create([
                'api_request_log_id' => $apiRequestLog->id,
                'payload' => $samplePayload,
            ]);

            // cek apakah model menggunakan timestamps
            $model = new Kpc();
            $useTimestamps = property_exists($model, 'timestamps') ? $model->timestamps : true;

            $batch = [];
            foreach ($data as $d) {
                if (empty($d)) continue;
                $row = [

                ];
                if ($useTimestamps) {
                    $row['created_at'] = now();
                    $row['updated_at'] = now();
                }
                $batch[] = $row;
            }

            DB::transaction(function () use ($batch, $apiRequestLog, $useTimestamps) {
                if (!empty($batch)) {
                    $processed = 0;
                    $updateCols = [
                        'id' => $batch['ID_KPC'],
                        'id_regional' => $batch['Regional'],
                        'id_kprk' => $batch['ID_KPRK'],
                        'nomor_dirian' => $batch['NomorDirian'],
                        'nama' => $batch['Nama_KPC'],
                        'jenis_kantor' => $batch['Jenis_KPC'],
                        'alamat' => $batch['Alamat'],
                        'koordinat_longitude' => $batch['Longitude'],
                        'koordinat_latitude' => $batch['Latitude'],
                        'nomor_telpon' => $batch['Nomor_Telp'],
                        'nomor_fax' => $batch['Nomor_fax'],
                        'id_provinsi' => $batch['Provinsi'],
                        'id_kabupaten_kota' => $batch['Kabupaten_Kota'],
                        'id_kecamatan' => $batch['Kecamatan'],
                        'id_kelurahan' => $batch['Kelurahan'],
                        'tipe_kantor' => $batch['Status_Gedung_Kantor'],
                        'jam_kerja_senin_kamis' => $batch['JamKerjaSeninKamis'],
                        'jam_kerja_jumat' => $batch['JamKerjaJumat'],
                        'jam_kerja_sabtu' => $batch['JamKerjaSabtu'],
                        'frekuensi_antar_ke_alamat' => $batch['FrekuensiAntarKeAlamat'],
                        'frekuensi_antar_ke_dari_kprk' => $batch['FrekuensiKirimDariKeKprk'],
                        'jumlah_tenaga_kontrak' => $batch['JumlahTenagaKontrak'],
                        'kondisi_gedung' => $batch['KondisiGedung'],
                        'fasilitas_publik_dalam' => $batch['FasilitasPublikDalamKantor'],
                        'fasilitas_publik_halaman' => $batch['FasilitasPublikLuarKantor'],
                        'lingkungan_kantor' => $batch['LingkunganKantor'],
                        'lingkungan_sekitar_kantor' => $batch['LingkunganSekitarKantor'],
                        'tgl_sinkronisasi' => now(),
                        'tgl_update' => now(),
                        'jarak_ke_kprk' => $batch['Jarak'],
                    ];

                    foreach (array_chunk($batch, 500) as $chunk) {
                        KPC::upsert($chunk, ['id'], $updateCols);
                        $processed += count($chunk);
                    }

                    $apiRequestLog->update([
                        'successful_records' => $processed,
                        'status' => 'success',
                    ]);
                } else {
                    $apiRequestLog->update(['status' => 'no_data']);
                }
            });

            unset($data, $batch);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        } catch (\Throwable $e) {
            if (isset($apiRequestLog) && $apiRequestLog instanceof ApiRequestLog) {
                try { $apiRequestLog->update(['status' => 'failed']); } catch (\Throwable $_) {}
            }
            \Log::error('ProcessSyncKPCJob failed', [
                'endpoint' => $this->endpoint,
                'page' => $this->page,
                'perPage' => $this->perPage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function maxAttempts() { return 5; }
    public function backoff() { return 10; }
    public function timeout() { return 0; }
}
