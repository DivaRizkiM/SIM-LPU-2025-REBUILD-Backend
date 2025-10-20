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
    protected ?string $idKcp; // opsional (by id)

    // Biarkan $page/$perPage bebas type di call-site; cast di sini
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

            if ($this->idKcp) {
                // mode by-id (tanpa paging)
                $req  = Request::create('/', 'GET', [
                    'end_point' => $this->endpoint . '?nopend=' . $this->idKcp
                ]);
                if ($this->userAgent) $req->headers->set('User-Agent', $this->userAgent);

                $resp = $api->makeRequest($req);
                $rows = $resp['data'] ?? [];

                if (isset($rows[0])) {
                    ApiRequestPayloadLog::create([
                        'api_request_log_id' => $apiRequestLog->id,
                        'payload' => json_encode($rows[0]),
                    ]);
                }

                $affected = $this->persistRowsWithUpdateOrCreate($rows);
                $processed += $affected;
                $totalRows += $affected;
            } else {
                // mode paging penuh
                do {
                    $req  = Request::create('/', 'GET', [
                        'end_point' => $this->endpoint,
                        'page'      => $page,
                        'per_page'  => $perPage,
                    ]);
                    if ($this->userAgent) $req->headers->set('User-Agent', $this->userAgent);

                    $resp = $api->makeRequest($req);
                    $rows = $resp['data'] ?? [];

                    if ($page === $this->page && isset($rows[0])) {
                        ApiRequestPayloadLog::create([
                            'api_request_log_id' => $apiRequestLog->id,
                            'payload' => json_encode($rows[0]),
                        ]);
                    }

                    if (empty($rows)) break;

                    $affected = $this->persistRowsWithUpdateOrCreate($rows);
                    $processed += $affected;
                    $totalRows += $affected;

                    $apiRequestLog->update([
                        'successful_records' => $processed,
                        'status' => "Memproses halaman {$page}",
                    ]);

                    $page++;
                } while (count($rows) === $perPage);
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

    /**
     * Simpan data menggunakan updateOrCreate:
     * - Kunci: nomor_dirian (ID_KPC)
     * - Hanya update kolom yang punya nilai (skip null/empty-string) agar tidak menimpa data lama.
     * - Selalu set tgl_update & tgl_sinkronisasi.
     *
     * @param array $apiRows
     * @return int jumlah baris yang diproses
     */
    protected function persistRowsWithUpdateOrCreate(array $apiRows): int
    {
        if (empty($apiRows)) return 0;

        return DB::transaction(function () use ($apiRows) {
            $count = 0;

            foreach ($apiRows as $p) {
                $idKpc = isset($p['ID_KPC']) ? (int) $p['ID_KPC'] : null;
                if (!$idKpc) continue;

                // Build data baru
                $values = [
                    'id_regional'                => $this->nn($p['Regional'] ?? null),
                    'id_kprk'                    => $this->nn($p['ID_KPRK'] ?? null),
                    'nomor_dirian'               => $this->nn($p['NomorDirian'] ?? null),
                    'nama'                       => $this->nn($p['Nama_KPC'] ?? null),
                    'jenis_kantor'               => $this->nn($p['Jenis_KPC'] ?? null),
                    'alamat'                     => $this->nn($p['Alamat'] ?? null),
                    'koordinat_latitude'         => $this->nn($p['Latitude'] ?? null),
                    'koordinat_longitude'        => $this->nn($p['Longitude'] ?? null),
                    'nomor_telpon'               => $this->nn($p['Nomor_Telp'] ?? null),
                    'nomor_fax'                  => $this->nn($p['Nomor_fax'] ?? null),
                    'id_provinsi'                => $this->nn($p['Provinsi'] ?? null),
                    'id_kabupaten_kota'          => $this->nn($p['Kabupaten_Kota'] ?? null),
                    'id_kecamatan'               => $this->nn($p['Kecamatan'] ?? null),
                    'id_kelurahan'               => $this->nn($p['Kelurahan'] ?? null),
                    'tipe_kantor'                => $this->nn($p['Status_Gedung_Kantor'] ?? null),
                    'jam_kerja_senin_kamis'      => $this->nn($p['JamKerjaSeninKamis'] ?? null),
                    'jam_kerja_jumat'            => $this->nn($p['JamKerjaJumat'] ?? null),
                    'jam_kerja_sabtu'            => $this->nn($p['JamKerjaSabtu'] ?? null),
                    'frekuensi_antar_ke_alamat'  => $this->nn($p['FrekuensiAntarKeAlamat'] ?? null),
                    'frekuensi_antar_ke_dari_kprk'=> $this->nn($p['FrekuensiKirimDariKeKprk'] ?? null),
                    'jumlah_tenaga_kontrak'      => $this->nn($p['JumlahTenagaKontrak'] ?? null),
                    'kondisi_gedung'             => $this->nn($p['KondisiGedung'] ?? null),
                    'fasilitas_publik_dalam'     => $this->nn($p['FasilitasPublikDalamKantor'] ?? null),
                    'fasilitas_publik_halaman'   => $this->nn($p['FasilitasPublikLuarKantor'] ?? null),
                    'lingkungan_kantor'          => $this->nn($p['LingkunganKantor'] ?? null),
                    'lingkungan_sekitar_kantor'  => $this->nn($p['LingkunganSekitarKantor'] ?? null),
                    'jarak_ke_kprk'              => $this->nn($p['Jarak'] ?? null),
                    'tgl_sinkronisasi'           => now(),
                    'tgl_update'                 => now(),
                ];

                // Hapus value yang null biar gak niban isi lama
                $values = array_filter($values, fn($v) => !is_null($v));

                // Update jika sudah ada, insert jika belum
                Kpc::updateOrCreate(['id' => $idKpc], $values);

                $count++;
            }

            return $count;
        }, 3);
    }


    /**
     * Normalizer: "" → null, trim string.
     */
    protected function nn($v)
    {
        if (!isset($v)) return null;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') return null;
        }
        return $v;
    }

    public function maxAttempts() { return 5; }
    public function backoff()     { return 10; }
    public function timeout()     { return 0; }
}
