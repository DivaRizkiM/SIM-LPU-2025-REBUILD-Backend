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

class FetchDaftarKpcAndSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $userAgent;

    public function __construct(?string $userAgent = null)
    {
        $this->userAgent = $userAgent;
    }

    public function handle(): void
    {
        $serverIpAddress = gethostbyname(gethostname());
        $agent = new Agent();
        if ($this->userAgent) $agent->setUserAgent($this->userAgent);
        $platform_request = $agent->platform() . '/' . $agent->browser();

        $api = new ApiController();
        $log = ApiRequestLog::create([
            'komponen'           => 'KPC',
            'tanggal'            => now(),
            'ip_address'         => $serverIpAddress,
            'platform_request'   => $platform_request,
            'successful_records' => 0,
            'total_records'      => 0,
            'available_records'  => 0,
            'status'             => 'Memulai (daftar_kpc)',
        ]);

        try {
            // 1) Ambil daftar KPC
            $listReq = Request::create('/', 'GET', ['end_point' => 'daftar_kpc']);
            if ($this->userAgent) $listReq->headers->set('User-Agent', $this->userAgent);

            $listResp   = $api->makeRequest($listReq);
            $daftar     = $listResp['data'] ?? [];
            $totalAvail = is_array($daftar) ? count($daftar) : 0;

            $log->update([
                'available_records' => $totalAvail,
                'total_records'     => $totalAvail,
                'status'            => 'Mengambil profil_kpc per nopend',
            ]);

            $processed = 0;

            // 2) Loop nopend → ambil profil & simpan
            foreach ($daftar as $i => $row) {
                $nopend = isset($row['nopend']) ? (string) $row['nopend'] : '';
                if ($nopend === '') continue;

                $profilReq = Request::create('/', 'GET', [
                    'end_point' => 'profil_kpc?nopend=' . $nopend
                ]);
                if ($this->userAgent) $profilReq->headers->set('User-Agent', $this->userAgent);

                $profilResp = $api->makeRequest($profilReq);
                $items      = $profilResp['data'] ?? [];

                if ($i === 0 && isset($items[0])) {
                    ApiRequestPayloadLog::create([
                        'api_request_log_id' => $log->id,
                        'payload'            => json_encode($items[0]),
                    ]);
                }

                $affected = $this->persistWithUpdateOrCreate($items);
                $processed += $affected;

                // optional progress tiap 100
                if ($processed % 100 === 0) {
                    $log->update([
                        'successful_records' => $processed,
                        'status'             => "Memproses {$processed}/{$totalAvail}",
                    ]);
                }
            }

            $log->update([
                'successful_records' => $processed,
                'status'             => 'success',
            ]);

        } catch (\Throwable $e) {
            $log->update(['status' => 'failed']);
            Log::error('FetchDaftarKpcAndSyncJob failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function persistWithUpdateOrCreate(array $apiRows): int
    {
        if (empty($apiRows)) return 0;

        return DB::transaction(function () use ($apiRows) {
            $count = 0;

            foreach ($apiRows as $p) {
                $idKpc = isset($p['ID_KPC']) ? (int) $p['ID_KPC'] : null;
                if (!$idKpc) continue;

                // Hanya kirim kolom yang ada nilainya (biar gak niban null)
                $vals = [
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

                $vals = array_filter($vals, fn($v) => !is_null($v));

                // id = ID_KPC (sesuai maumu)
                Kpc::updateOrCreate(['id' => $idKpc], $vals);

                $count++;
            }

            return $count;
        }, 3);
    }

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
