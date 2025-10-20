<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\Kpc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class ProcessSyncKPCJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $endpoint;
    protected ?string $userAgent;
    protected int $page;
    protected int $perPage;
    protected ?string $idKcp;

    // >>> Perhatikan: $page & $perPage TANPA type-hint di signature
    public function __construct(string $endpoint, ?string $userAgent = null, $page = 1, $perPage = 1000, ?string $idKcp = null)
    {
        $this->endpoint  = $endpoint;
        $this->userAgent = $userAgent;
        $this->page      = (int) $page;
        $this->perPage   = (int) $perPage;
        $this->idKcp     = $idKcp;
    }

    public function handle(): void
    {
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        if ($this->userAgent) $agent->setUserAgent($this->userAgent);
        $platform_request = $agent->platform() . '/' . $agent->browser();

        $api = new ApiController();
        $apiRequestLog = ApiRequestLog::create([
            'komponen' => 'KPC',
            'tanggal' => now(),
            'ip_address' => $serverIpAddress,
            'platform_request' => $platform_request,
            'successful_records' => 0,
            'total_records' => 0,
            'available_records' => 0,
            'status' => 'Memulai',
        ]);

        try {
            $page       = (int) $this->page;
            $perPage    = (int) $this->perPage;
            $processed  = 0;
            $totalRows  = 0;
            $buffer     = [];

            if ($this->idKcp) {
                // by-id (tanpa paging)
                $req  = Request::create('/', 'GET', ['end_point' => $this->endpoint . '?nopend=' . $this->idKcp]);
                $resp = $api->makeRequest($req);
                $rows = $resp['data'] ?? [];

                if (isset($rows[0])) {
                    ApiRequestPayloadLog::create([
                        'api_request_log_id' => $apiRequestLog->id,
                        'payload' => json_encode($rows[0]),
                    ]);
                }

                foreach ($rows as $r) $buffer[] = $this->mapRow($r);
                $flushed = count($buffer);
                if ($flushed > 0) {
                    $this->flushUpsert($buffer);
                    $processed += $flushed;
                    $totalRows += $flushed;
                }
            } else {
                // mode paging penuh
                do {
                    $req  = Request::create('/', 'GET', [
                        'end_point' => $this->endpoint,
                        'page'      => $page,
                        'per_page'  => $perPage
                    ]);
                    $resp = $api->makeRequest($req);
                    $rows = $resp['data'] ?? [];

                    if ($page === $this->page && isset($rows[0])) {
                        ApiRequestPayloadLog::create([
                            'api_request_log_id' => $apiRequestLog->id,
                            'payload' => json_encode($rows[0]),
                        ]);
                    }

                    if (empty($rows)) break;

                    foreach ($rows as $r) $buffer[] = $this->mapRow($r);
                    $totalRows += count($rows);

                    // flush semua yang terkumpul (bisa >500, nanti di-chunk di flushUpsert)
                    if (count($buffer) >= 500) {
                        $flushed = count($buffer);   // <<— HITUNG DULU
                        $this->flushUpsert($buffer); // akan mengosongkan $buffer
                        $processed += $flushed;      // <<— TAMBAH SESUAI JUMLAH SEBENARNYA
                        $apiRequestLog->update([
                            'successful_records' => $processed,
                            'status' => "Memproses halaman {$page}",
                        ]);
                    }

                    $page++;
                } while (count($rows) === $perPage);

                // flush sisa
                if (!empty($buffer)) {
                    $flushed = count($buffer);
                    $this->flushUpsert($buffer);
                    $processed += $flushed;
                }
            }

            $apiRequestLog->update([
                'successful_records' => $processed,
                'total_records'      => $totalRows,
                'available_records'  => $totalRows,
                'status'             => 'success',
            ]);
        } catch (\Throwable $e) {
            $apiRequestLog->update(['status' => 'failed']);
            Log::error('ProcessSyncKPCJob failed', [
                'endpoint' => $this->endpoint,
                'page'     => $this->page,
                'perPage'  => $this->perPage,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function mapRow(array $p): array
    {
        return [
            'id'                         => $p['ID_KPC'] ?? null,
            'id_regional'                => $p['Regional'] ?? null,
            'id_kprk'                    => $p['ID_KPRK'] ?? null,
            'nomor_dirian'               => $p['NomorDirian'] ?? null,
            'nama'                       => $p['Nama_KPC'] ?? null,
            'jenis_kantor'               => $p['Jenis_KPC'] ?? null,
            'alamat'                     => $p['Alamat'] ?? null,
            'koordinat_longitude'        => $p['Longitude'] ?? null,
            'koordinat_latitude'         => $p['Latitude'] ?? null,
            'nomor_telpon'               => $p['Nomor_Telp'] ?? null,
            'nomor_fax'                  => $p['Nomor_fax'] ?? null,
            'id_provinsi'                => $p['Provinsi'] ?? null,
            'id_kabupaten_kota'          => $p['Kabupaten_Kota'] ?? null,
            'id_kecamatan'               => $p['Kecamatan'] ?? null,
            'id_kelurahan'               => $p['Kelurahan'] ?? null,
            'tipe_kantor'                => $p['Status_Gedung_Kantor'] ?? null,
            'jam_kerja_senin_kamis'      => $p['JamKerjaSeninKamis'] ?? null,
            'jam_kerja_jumat'            => $p['JamKerjaJumat'] ?? null,
            'jam_kerja_sabtu'            => $p['JamKerjaSabtu'] ?? null,
            'frekuensi_antar_ke_alamat'  => $p['FrekuensiAntarKeAlamat'] ?? null,
            'frekuensi_antar_ke_dari_kprk'=> $p['FrekuensiKirimDariKeKprk'] ?? null,
            'jumlah_tenaga_kontrak'      => $p['JumlahTenagaKontrak'] ?? null,
            'kondisi_gedung'             => $p['KondisiGedung'] ?? null,
            'fasilitas_publik_dalam'     => $p['FasilitasPublikDalamKantor'] ?? null,
            'fasilitas_publik_halaman'   => $p['FasilitasPublikLuarKantor'] ?? null,
            'lingkungan_kantor'          => $p['LingkunganKantor'] ?? null,
            'lingkungan_sekitar_kantor'  => $p['LingkunganSekitarKantor'] ?? null,
            'tgl_sinkronisasi'           => now(),
            'tgl_update'                 => now(),
            'jarak_ke_kprk'              => $p['Jarak'] ?? null,
        ];
    }

    protected function flushUpsert(array &$rows): void
    {
        if (empty($rows)) return;

        DB::transaction(function () use (&$rows) {
            $updateCols = [
                'id_regional','id_kprk','nomor_dirian','nama','jenis_kantor',
                'alamat','koordinat_longitude','koordinat_latitude','nomor_telpon','nomor_fax',
                'id_provinsi','id_kabupaten_kota','id_kecamatan','id_kelurahan','tipe_kantor',
                'jam_kerja_senin_kamis','jam_kerja_jumat','jam_kerja_sabtu',
                'frekuensi_antar_ke_alamat','frekuensi_antar_ke_dari_kprk','jumlah_tenaga_kontrak',
                'kondisi_gedung','fasilitas_publik_dalam','fasilitas_publik_halaman',
                'lingkungan_kantor','lingkungan_sekitar_kantor',
                'tgl_sinkronisasi','tgl_update','jarak_ke_kprk'
            ];
            foreach (array_chunk($rows, 500) as $chunk) {
                Kpc::upsert($chunk, ['id'], $updateCols);
            }
        }, 3);

        $rows = [];
    }

    public function maxAttempts() { return 5; }
    public function backoff()     { return 10; }
    public function timeout()     { return 0; }
}
