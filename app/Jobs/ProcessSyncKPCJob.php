<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\Kpc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSyncKPCJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $endpointList = 'daftar_kpc',
        public ?string $userAgent = null,
        public int $page = 1,
        public int $perPage = 1000
    ) {}

    public function handle()
    {
        $api = new ApiController();

        // 1) Ambil daftar_kpc halaman $this->page
        $listReq = HttpRequest::create('/', 'GET', [
            'end_point' => $this->endpointList,
            'page'      => $this->page,
            'per_page'  => $this->perPage,
        ]);

        $listResp = $api->makeRequest($listReq);
        $listData = $listResp['data'] ?? [];

        if (empty($listData)) {
            Log::info('ProcessSyncKPCJob: daftar_kpc kosong pada page '.$this->page);
            return;
        }

        // Ambil hanya nopend, buang null
        $nopends = array_values(array_filter(array_map(
            fn($row) => is_array($row) ? ($row['nopend'] ?? null) : null,
            $listData
        )));

        // Logging awal
        $apiRequestLog = ApiRequestLog::create([
            'komponen'          => 'KPC',
            'tanggal'           => now(),
            'ip_address'        => gethostbyname(gethostname()),
            'platform_request'  => $this->userAgent ?? 'unknown',
            'successful_records'=> 0,
            'total_records'     => count($nopends),
            'available_records' => count($nopends),
            'status'            => 'Memproses Batch',
        ]);

        ApiRequestPayloadLog::create([
            'api_request_log_id' => $apiRequestLog->id,
            'payload'            => json_encode(['sample_nopend' => $nopends[0] ?? null]),
        ]);

        // 2) Untuk setiap nopend, call profil_kpc?nopend=...
        $rows = [];
        $now = now();

        // batasi agar aman (misal 100 per batch)
        foreach (array_chunk($nopends, 100) as $chunk) {
            foreach ($chunk as $nopend) {
                try {
                    $profilReq = HttpRequest::create('/', 'GET', [
                        'end_point' => 'profil_kpc',
                        'nopend'    => $nopend,
                    ]);
                    $profilResp = $api->makeRequest($profilReq);
                    $payload = $profilResp['data'] ?? null;

                    if (!$payload) continue;

                    // Normalisasi: API bisa return array atau objek tunggal
                    $item = is_array($payload) && Arr::isAssoc($payload) ? $payload
                         : (is_array($payload) && !empty($payload) ? $payload[0] : null);

                    if (!$item) continue;

                    // 3) Mapping field API → kolom DB (SESUAIKAN DENGAN SHEMA KAMU)
                    $row = [
                        // Primary/unique key: kamu tadi bilang id pakai id_kpc
                        // kalau unique-nya di 'id_kpc', maka jadikan ini kolom unique di DB.
                        'id_kpc'                  => $item['ID_KPC']          ?? $item['id_kpc'] ?? null,
                        'nopend'                  => $item['NoPend']          ?? $item['nopend'] ?? null,
                        'id_regional'             => $item['Regional']        ?? null,
                        'id_kprk'                 => $item['ID_KPRK']         ?? null,
                        'nomor_dirian'            => $item['NomorDirian']     ?? null,
                        'nama'                    => $item['Nama_KPC']        ?? $item['nama'] ?? null,
                        'jenis_kantor'            => $item['Jenis_KPC']       ?? null,
                        'alamat'                  => $item['Alamat']          ?? null,
                        'koordinat_longitude'     => $item['Longitude']       ?? null,
                        'koordinat_latitude'      => $item['Latitude']        ?? null,
                        'nomor_telpon'            => $item['Nomor_Telp']      ?? null,
                        'nomor_fax'               => $item['Nomor_fax']       ?? null,
                        'id_provinsi'             => $item['Provinsi']        ?? null,
                        'id_kabupaten_kota'       => $item['Kabupaten_Kota']  ?? null,
                        'id_kecamatan'            => $item['Kecamatan']       ?? null,
                        'id_kelurahan'            => $item['Kelurahan']       ?? null,
                        'tipe_kantor'             => $item['Status_Gedung_Kantor'] ?? null,
                        'jam_kerja_senin_kamis'   => $item['JamKerjaSeninKamis']   ?? null,
                        'jam_kerja_jumat'         => $item['JamKerjaJumat']        ?? null,
                        'jam_kerja_sabtu'         => $item['JamKerjaSabtu']        ?? null,
                        'frekuensi_antar_ke_alamat'       => $item['FrekuensiAntarKeAlamat']  ?? null,
                        'frekuensi_antar_ke_dari_kprk'    => $item['FrekuensiKirimDariKeKprk']?? null,
                        'jumlah_tenaga_kontrak'   => $item['JumlahTenagaKontrak'] ?? null,
                        'kondisi_gedung'          => $item['KondisiGedung']       ?? null,
                        'fasilitas_publik_dalam'  => $item['FasilitasPublikDalamKantor'] ?? null,
                        'fasilitas_publik_halaman'=> $item['FasilitasPublikLuarKantor']  ?? null,
                        'lingkungan_kantor'       => $item['LingkunganKantor']   ?? null,
                        'lingkungan_sekitar_kantor'=> $item['LingkunganSekitarKantor'] ?? null,
                        'jarak_ke_kprk'           => $item['Jarak'] ?? null,
                        'tgl_sinkronisasi'        => $now,
                        'tgl_update'              => $now,
                        'created_at'              => $now,
                        'updated_at'              => $now,
                    ];

                    // kalau id_kpc kosong tapi nopend ada, kamu bisa pakai 'nopend' sbg unique key alternatif
                    $rows[] = $row;
                } catch (\Throwable $th) {
                    Log::warning('profil_kpc gagal', ['nopend' => $nopend, 'err' => $th->getMessage()]);
                    // lanjut saja; biar yang lain tetap diproses
                }
            }

            // throttle kecil biar aman ke API (opsional)
            usleep(150000); // 150ms
        }

        // 4) Upsert per chunk
        $processed = 0;
        if (!empty($rows)) {
            DB::transaction(function () use (&$processed, $rows) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    // tentukan unique-by sesuai skema: id_kpc atau nopend
                    Kpc::upsert(
                        $chunk,
                        ['id_kpc'], // <- unique key di DB
                        [
                            'nopend','id_regional','id_kprk','nomor_dirian','nama','jenis_kantor','alamat',
                            'koordinat_longitude','koordinat_latitude','nomor_telpon','nomor_fax',
                            'id_provinsi','id_kabupaten_kota','id_kecamatan','id_kelurahan','tipe_kantor',
                            'jam_kerja_senin_kamis','jam_kerja_jumat','jam_kerja_sabtu',
                            'frekuensi_antar_ke_alamat','frekuensi_antar_ke_dari_kprk',
                            'jumlah_tenaga_kontrak','kondisi_gedung','fasilitas_publik_dalam',
                            'fasilitas_publik_halaman','lingkungan_kantor','lingkungan_sekitar_kantor',
                            'jarak_ke_kprk','tgl_sinkronisasi','tgl_update','updated_at'
                        ]
                    );
                    $processed += count($chunk);
                }
            });
        }

        $apiRequestLog->update([
            'successful_records' => $processed,
            'status'             => $processed > 0 ? 'success' : 'no_data',
        ]);
    }

    public function maxAttempts() { return 5; }
    public function backoff() { return 10; }
    public function timeout() { return 0; }
}
